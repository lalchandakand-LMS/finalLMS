<?php

namespace App\Http\Controllers;

use App\Models\QuizContest;
use App\Models\QuizContestEntry;
use App\Models\QuizContestWinner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizContestController extends Controller
{
    public function index(): View
    {
        $contests = QuizContest::query()
            ->where('status', 'published')
            ->withCount(['entries', 'questions'])
            ->with('winners.user')
            ->latest()
            ->paginate(12);

        return view('contests.index', compact('contests'));
    }

    public function show(QuizContest $contest): View
    {
        abort_unless($contest->status === 'published' || $contest->drawn_at, 404);
        $contest->load(['questions', 'winners.user']);
        $entry = auth()->check()
            ? $contest->entries()->where('user_id', auth()->id())->first()
            : null;

        return view('contests.show', compact('contest', 'entry'));
    }

    public function submit(Request $request, QuizContest $contest): RedirectResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        DB::transaction(function () use ($contest, $validated): void {
            $lockedContest = QuizContest::query()->lockForUpdate()->findOrFail($contest->id);
            abort_unless($lockedContest->status === 'published'
                && now()->between($lockedContest->starts_at, $lockedContest->ends_at), 422, 'This quiz is not accepting answers now.');

            $questions = $lockedContest->questions()->get();
            $expectedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
            $submittedIds = collect(array_keys($validated['answers']))->map(fn ($id) => (string) $id)->sort()->values();
            abort_unless($expectedIds->all() === $submittedIds->all(), 422, 'Please answer every question in this quiz.');

            abort_if($lockedContest->entries()->where('user_id', auth()->id())->exists(), 409, 'You have already participated in this contest.');

            $score = $questions->sum(function ($question) use ($validated): int {
                return (int) ($validated['answers'][$question->id] === $question->correct_option);
            });

            QuizContestEntry::create([
                'quiz_contest_id' => $lockedContest->id,
                'user_id' => auth()->id(),
                'answers' => $validated['answers'],
                'score' => $score,
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('contests.show', $contest)->with('success', 'Your answers have been submitted. Your score has been recorded. Results will be ranked by score and submission time.');
    }

    public function adminIndex(): View
    {
        $contests = QuizContest::query()
            ->withCount(['entries', 'questions'])
            ->with('winners.user')
            ->latest()
            ->paginate(15);

        return view('admin.contests.index', compact('contests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'prize_description' => ['required', 'string', 'max:500'],
            'winner_count' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.question' => ['required', 'string', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'size:4'],
            'questions.*.options.*' => ['required', 'string', 'max:300'],
            'questions.*.correct_option' => ['required', 'integer', 'between:0,3'],
        ]);

        DB::transaction(function () use ($validated): void {
            $contest = QuizContest::create([
                'created_by' => auth()->id(),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'prize_description' => $validated['prize_description'],
                'winner_count' => $validated['winner_count'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'status' => 'published',
            ]);

            foreach ($validated['questions'] as $position => $question) {
                $contest->questions()->create([
                    'question' => $question['question'],
                    'options' => array_values($question['options']),
                    'correct_option' => $question['correct_option'],
                    'position' => $position + 1,
                ]);
            }
        });

        return redirect()->route('admin.contests.index')->with('success', 'Quiz contest published successfully.');
    }

    public function updateStatus(Request $request, QuizContest $contest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:published,paused,closed'],
        ]);

        DB::transaction(function () use ($contest, $validated): void {
            $lockedContest = QuizContest::query()->lockForUpdate()->findOrFail($contest->id);
            abort_if($lockedContest->drawn_at !== null, 409, 'Published results cannot be changed.');
            $nextStatus = $validated['status'];

            if ($nextStatus === 'paused') {
                abort_unless($lockedContest->status === 'published' && now()->lessThan($lockedContest->ends_at), 422, 'Only an active contest can be paused.');
            } elseif ($nextStatus === 'published') {
                abort_unless($lockedContest->status === 'paused' && now()->lessThan($lockedContest->ends_at), 422, 'Only a paused contest before its deadline can be resumed.');
            } else {
                abort_unless(in_array($lockedContest->status, ['published', 'paused'], true)
                    && now()->greaterThanOrEqualTo($lockedContest->ends_at), 422, 'A contest can only be closed after its scheduled end time.');
            }

            $lockedContest->update(['status' => $nextStatus]);
        });

        return redirect()->route('admin.contests.index')->with('success', 'Contest status updated.');
    }

    public function publishResults(QuizContest $contest): RedirectResponse
    {
        $winnerCount = DB::transaction(function () use ($contest): int {
            $lockedContest = QuizContest::query()->lockForUpdate()->findOrFail($contest->id);
            abort_if($lockedContest->drawn_at !== null, 409, 'The results have already been published.');
            abort_unless(in_array($lockedContest->status, ['published', 'closed'], true) && now()->greaterThanOrEqualTo($lockedContest->ends_at), 422, 'The contest must end before results can be published.');

            $entries = $lockedContest->entries()
                ->orderByDesc('score')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get();
            abort_if($entries->isEmpty(), 422, 'There are no quiz submissions to rank.');

            // Transparent skill-based ranking: highest score, then earliest submission,
            // then lowest entry ID as a deterministic final tie-break.
            $selected = $entries->take(min($lockedContest->winner_count, $entries->count()));
            foreach ($selected as $index => $entry) {
                QuizContestWinner::create([
                    'quiz_contest_id' => $lockedContest->id,
                    'quiz_contest_entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'rank' => $index + 1,
                    'prize_snapshot' => $lockedContest->prize_description,
                    'drawn_by' => auth()->id(),
                    'drawn_at' => now(),
                ]);
            }

            $lockedContest->update(['status' => 'closed', 'drawn_at' => now()]);

            return count($selected);
        });

        return redirect()->route('admin.contests.index')->with('success', $winnerCount.' top participant(s) published by score and submission time. Results are locked.');
    }
}
