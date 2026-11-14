<?php

namespace App\Http\Controllers;

use App\Models\QuestionBankQuestion;
use App\Models\QuestionSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminQuestionSetController extends Controller
{
    public function index(): View
    {
        $questionSets = QuestionSet::query()
            ->with('creator')
            ->withCount(['questions', 'courses'])
            ->latest()
            ->paginate(20);

        return view('admin.question-sets.index', compact('questionSets'));
    }

    public function create(): View
    {
        $questionSet = new QuestionSet(['status' => 'draft']);
        $questions = collect();
        $bankQuestions = QuestionBankQuestion::with('teacher:id,name')->latest()->limit(500)->get();

        return view('admin.question-sets.form', compact('questionSet', 'questions', 'bankQuestions'));
    }

    public function edit(QuestionSet $questionSet): View
    {
        $questionSet->load('questions');
        $questions = $questionSet->questions;
        $bankQuestions = QuestionBankQuestion::with('teacher:id,name')->latest()->limit(500)->get();

        return view('admin.question-sets.form', compact('questionSet', 'questions', 'bankQuestions'));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validatedPayload($request);

        DB::transaction(function () use ($request, $data, $items): void {
            $set = QuestionSet::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'created_by' => $request->user()->id,
            ]);
            $set->questions()->createMany($items);
        });

        return redirect()->route('admin.question-sets.index')->with('success', __('Question set created.'));
    }

    public function update(Request $request, QuestionSet $questionSet): RedirectResponse
    {
        [$data, $items] = $this->validatedPayload($request);

        DB::transaction(function () use ($questionSet, $data, $items): void {
            $set = QuestionSet::query()->lockForUpdate()->findOrFail($questionSet->id);
            $set->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);
            $set->questions()->delete();
            $set->questions()->createMany($items);
        });

        return redirect()->route('admin.question-sets.index')->with('success', __('Question set updated.'));
    }

    public function destroy(QuestionSet $questionSet): RedirectResponse
    {
        $archived = DB::transaction(function () use ($questionSet): bool {
            $set = QuestionSet::query()->lockForUpdate()->findOrFail($questionSet->id);
            if ($set->courses()->exists() || $set->attempts()->exists()) {
                $set->update(['status' => 'archived']);
                return true;
            }

            $set->delete();
            return false;
        });

        return back()->with('success', $archived
            ? __('Question set archived because it is attached to a course.')
            : __('Question set deleted.'));
    }

    /**
     * @return array{0: array, 1: array<int, array<string, mixed>>}
     */
    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'source_question_ids' => ['nullable', 'array', 'max:200'],
            'source_question_ids.*' => ['integer', 'distinct', 'exists:question_bank_questions,id'],
            'source_question_positions' => ['nullable', 'array'],
            'source_question_positions.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'questions' => ['nullable', 'array', 'max:200'],
            'questions.*.type' => ['required', Rule::in(['mcq', 'true_false', 'short_answer'])],
            'questions.*.question' => ['required', 'string', 'max:3000'],
            'questions.*.position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'questions.*.options' => ['nullable', 'array', 'max:4'],
            'questions.*.options.*' => ['required', 'string', 'max:500', 'distinct'],
            'questions.*.correct_option' => ['nullable', 'integer', 'between:0,3'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:2000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:3000'],
            'questions.*.difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'questions.*.tags' => ['nullable', 'string', 'max:1000'],
            'questions.*.marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $sourceIds = array_values($data['source_question_ids'] ?? []);
        $sourceQuestions = QuestionBankQuestion::query()->whereIn('id', $sourceIds)->get()->keyBy('id');
        abort_unless($sourceQuestions->count() === count($sourceIds), 422, __('One or more selected bank questions no longer exist.'));

        $items = [];
        $sourcePositions = $data['source_question_positions'] ?? [];
        foreach ($sourceIds as $index => $id) {
            $source = $sourceQuestions->get($id);
            $items[] = [
                'source_question_id' => $source->id,
                'type' => 'mcq',
                'question' => $source->question,
                'options' => $source->options,
                'correct_option' => $source->correct_option,
                'correct_answer' => null,
                'explanation' => null,
                'difficulty' => $source->difficulty ?: 'medium',
                'tags' => array_values(array_filter([$source->subject, $source->category])),
                'marks' => $source->marks,
                'position' => (int) ($sourcePositions[$id] ?? (($index + 1) * 10)),
            ];
        }

        foreach ($data['questions'] ?? [] as $question) {
            $type = $question['type'];
            $options = null;
            $correctOption = null;
            $correctAnswer = null;

            if ($type === 'mcq') {
                $options = array_values($question['options'] ?? []);
                if (count($options) !== 4 || ! isset($question['correct_option']) || (int) $question['correct_option'] > 3) {
                    throw ValidationException::withMessages([
                        'questions' => __('Each MCQ needs four different options and one correct option.'),
                    ]);
                }
                $correctOption = (int) $question['correct_option'];
            } elseif ($type === 'true_false') {
                if (! in_array((string) ($question['correct_option'] ?? ''), ['0', '1'], true)) {
                    throw ValidationException::withMessages([
                        'questions' => __('True/False questions need True or False as the correct answer.'),
                    ]);
                }
                $options = ['True', 'False'];
                $correctOption = (int) $question['correct_option'];
            } else {
                $correctAnswer = trim((string) ($question['correct_answer'] ?? ''));
                if ($correctAnswer === '') {
                    throw ValidationException::withMessages([
                        'questions' => __('Short-answer questions need an expected answer for grading.'),
                    ]);
                }
            }

            $tags = collect(explode(',', (string) ($question['tags'] ?? '')))
                ->map(fn ($tag) => trim($tag))
                ->filter()
                ->map(fn ($tag) => mb_substr($tag, 0, 40))
                ->unique(fn ($tag) => mb_strtolower($tag))
                ->take(20)->values()->all();

            $items[] = [
                'source_question_id' => null,
                'type' => $type,
                'question' => $question['question'],
                'options' => $options,
                'correct_option' => $correctOption,
                'correct_answer' => $correctAnswer,
                'explanation' => $question['explanation'] ?? null,
                'difficulty' => $question['difficulty'] ?? 'medium',
                'tags' => $tags,
                'marks' => (int) $question['marks'],
                'position' => (int) ($question['position'] ?? ((count($items) + 1) * 10)),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'questions' => __('Select at least one Question Bank item or add a question to this set.'),
            ]);
        }

        usort($items, fn (array $left, array $right): int => $left['position'] <=> $right['position']);
        foreach ($items as $index => &$item) {
            $item['position'] = ($index + 1) * 10;
        }
        unset($item);

        return [$data, $items];
    }
}
