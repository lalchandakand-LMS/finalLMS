<?php

namespace Aloms\PrepQuestionBank;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PrepQuestionBankController extends Controller
{
    private const TRACKS = ['bcs', 'bank_job', 'admission'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'track' => ['nullable', Rule::in(self::TRACKS)],
            'subject' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['mcq', 'written'])],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = DB::table('aec_prep_questions')
            ->when($filters['track'] ?? null, fn ($q, $v) => $q->where('track', $v))
            ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('subject', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('source_year', $v))
            ->when($filters['search'] ?? null, function ($q, $term): void {
                $q->where(fn ($nested) => $nested->where('question', 'like', '%'.trim($term).'%')
                    ->orWhere('topic', 'like', '%'.trim($term).'%'));
            });

        $questions = $query->latest()->paginate(25)->withQueryString();
        $counts = DB::table('aec_prep_questions')->selectRaw('track, COUNT(*) as total')->groupBy('track')->pluck('total', 'track');
        $subjects = DB::table('aec_prep_questions')->when($filters['track'] ?? null, fn ($q, $v) => $q->where('track', $v))
            ->distinct()->orderBy('subject')->pluck('subject');
        $sets = DB::table('aec_prep_question_sets')->orderByDesc('created_at')->limit(8)->get();

        return view('prep-question-bank::admin.index', compact('questions', 'filters', 'counts', 'subjects', 'sets'));
    }

    public function createQuestion(): View
    {
        return view('prep-question-bank::admin.question-form', ['question' => null]);
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $this->validatedQuestion($request);
        $data['created_by'] = $request->user()->id;
        $id = DB::table('aec_prep_questions')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('admin.prep-question-bank.index')->with('success', __('Question added to the preparation question bank.'));
    }

    public function editQuestion(int $question): View
    {
        $record = DB::table('aec_prep_questions')->where('id', $question)->first();
        abort_unless($record, 404);
        $record->options = $record->options ? json_decode($record->options, true) : [];
        return view('prep-question-bank::admin.question-form', ['question' => $record]);
    }

    public function updateQuestion(Request $request, int $question): RedirectResponse
    {
        abort_unless(DB::table('aec_prep_questions')->where('id', $question)->exists(), 404);
        $data = $this->validatedQuestion($request);
        DB::table('aec_prep_questions')->where('id', $question)->update($data + ['updated_at' => now()]);
        return redirect()->route('admin.prep-question-bank.index')->with('success', __('Question updated.'));
    }

    public function destroyQuestion(int $question): RedirectResponse
    {
        $used = DB::table('aec_prep_question_set_items')->where('source_question_id', $question)->exists();
        if ($used) {
            return back()->withErrors(['question' => __('This question is already used in an exam set. It is preserved to protect existing papers.')]);
        }
        DB::table('aec_prep_questions')->where('id', $question)->delete();
        return back()->with('success', __('Question deleted.'));
    }

    public function sets(): View
    {
        $sets = DB::table('aec_prep_question_sets')
            ->select('aec_prep_question_sets.*')
            ->selectSub(fn ($q) => $q->from('aec_prep_question_set_items')->whereColumn('question_set_id', 'aec_prep_question_sets.id')->selectRaw('COUNT(*)'), 'questions_count')
            ->orderByDesc('created_at')->paginate(20);
        return view('prep-question-bank::admin.sets', compact('sets'));
    }

    public function createSet(Request $request): View
    {
        $filters = $request->validate([
            'track' => ['nullable', Rule::in(self::TRACKS)],
            'subject' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['mcq', 'written'])],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
        ]);
        $questions = DB::table('aec_prep_questions')
            ->when($filters['track'] ?? null, fn ($q, $v) => $q->where('track', $v))
            ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('subject', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('source_year', $v))
            ->orderBy('subject')->orderBy('id')->limit(500)->get();
        return view('prep-question-bank::admin.set-form', compact('questions', 'filters'));
    }

    public function storeSet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'track' => ['required', Rule::in(self::TRACKS)],
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', Rule::in(['draft', 'ready'])],
            'question_ids' => ['nullable', 'array', 'max:200'],
            'question_ids.*' => ['integer', 'distinct', 'exists:aec_prep_questions,id'],
            'random_count' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $ids = array_values($data['question_ids'] ?? []);
        if ($ids === []) {
            throw ValidationException::withMessages(['question_ids' => __('Select at least one question for this paper.')]);
        }

        $questions = DB::table('aec_prep_questions')->whereIn('id', $ids)->where('track', $data['track'])->get();
        abort_unless($questions->count() === count($ids), 422, __('Selected questions must all belong to the selected exam track.'));
        if (($data['random_count'] ?? 0) > $questions->count()) {
            throw ValidationException::withMessages(['random_count' => __('Random question count exceeds selected questions.')]);
        }
        $selected = $questions->shuffle()->take($data['random_count'] ?? $questions->count())->values();

        $setId = DB::transaction(function () use ($request, $data, $selected): int {
            $id = DB::table('aec_prep_question_sets')->insertGetId([
                'track' => $data['track'], 'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'duration_minutes' => $data['duration_minutes'], 'status' => $data['status'],
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($selected as $position => $question) {
                DB::table('aec_prep_question_set_items')->insert([
                    'question_set_id' => $id, 'source_question_id' => $question->id,
                    'type' => $question->type, 'subject' => $question->subject, 'topic' => $question->topic,
                    'question' => $question->question, 'options' => $question->options,
                    'correct_option' => $question->correct_option, 'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation, 'marks' => $question->marks,
                    'position' => ($position + 1) * 10, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $id;
        });

        return redirect()->route('admin.prep-question-bank.sets.show', $setId)->with('success', __('Exam question paper created.'));
    }

    public function showSet(int $set): View
    {
        $paper = DB::table('aec_prep_question_sets')->where('id', $set)->first();
        abort_unless($paper, 404);
        $items = DB::table('aec_prep_question_set_items')->where('question_set_id', $set)->orderBy('position')->get();
        foreach ($items as $item) $item->options = $item->options ? json_decode($item->options, true) : [];
        $totalMarks = $items->sum('marks');
        return view('prep-question-bank::admin.paper', compact('paper', 'items', 'totalMarks'));
    }

    public function destroySet(int $set): RedirectResponse
    {
        DB::transaction(function () use ($set): void {
            DB::table('aec_prep_question_set_items')->where('question_set_id', $set)->delete();
            DB::table('aec_prep_question_sets')->where('id', $set)->delete();
        });
        return redirect()->route('admin.prep-question-bank.sets.index')->with('success', __('Question paper deleted.'));
    }

    private function validatedQuestion(Request $request): array
    {
        $data = $request->validate([
            'track' => ['required', Rule::in(self::TRACKS)],
            'subject' => ['required', 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:120'],
            'source_year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'source_name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(['mcq', 'written'])],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'question' => ['required', 'string', 'max:20000'],
            'options' => ['nullable', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:1000', 'distinct'],
            'correct_option' => ['nullable', 'integer', 'between:0,3'],
            'correct_answer' => ['nullable', 'string', 'max:10000'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        if ($data['type'] === 'mcq') {
            if (count($data['options'] ?? []) !== 4 || !isset($data['correct_option'])) {
                throw ValidationException::withMessages(['options' => __('MCQ questions require four distinct options and one correct answer.')]);
            }
            $data['options'] = json_encode(array_values($data['options']), JSON_THROW_ON_ERROR);
            $data['correct_option'] = (int) $data['correct_option'];
            $data['correct_answer'] = null;
        } else {
            $data['options'] = null;
            $data['correct_option'] = null;
        }
        return $data;
    }
}
