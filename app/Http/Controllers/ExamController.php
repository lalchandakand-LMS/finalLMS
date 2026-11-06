<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAnswer;
use App\Models\Enrollment;
use App\Models\QuestionBankQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function teacherIndex(Batch $batch): View
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $exams = $batch->exams()->withCount(['questions', 'attempts'])->latest()->get();
        $bankQuestions = QuestionBankQuestion::where('teacher_id', Auth::id())->latest()->get();

        return view('teacher.exams.index', compact('batch', 'exams', 'bankQuestions'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'pass_marks' => ['required', 'integer', 'min:0'],
            'questions_per_attempt' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions' => ['nullable', 'array', 'min:1', 'max:100'],
            'question_bank_ids' => ['nullable', 'array', 'min:1', 'max:500'],
            'question_bank_ids.*' => ['integer', 'distinct', 'exists:question_bank_questions,id'],
            'random_question_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.question' => ['required', 'string', 'max:3000'],
            'questions.*.options' => ['required', 'array', 'size:4'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.correct_option' => ['required', 'integer', 'between:0,3'],
            'questions.*.marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $selectedQuestions = collect($validated['questions'] ?? []);
        if (! empty($validated['question_bank_ids'])) {
            $bankQuestions = QuestionBankQuestion::query()
                ->whereIn('id', $validated['question_bank_ids'])
                ->where('teacher_id', Auth::id())
                ->inRandomOrder()
                ->get();

            abort_unless($bankQuestions->count() === count($validated['question_bank_ids']), 403, 'One or more selected questions do not belong to you.');
            $selectedCount = $validated['random_question_count'] ?? $bankQuestions->count();
            abort_if($selectedCount > $bankQuestions->count(), 422, 'Random question count exceeds the selected bank questions.');

            $selectedQuestions = $bankQuestions->shuffle()->take($selectedCount)->map(fn ($question) => [
                'question' => $question->question,
                'options' => $question->options,
                'correct_option' => $question->correct_option,
                'marks' => $question->marks,
            ]);
        }

        abort_if($selectedQuestions->isEmpty(), 422, 'Add questions or select questions from your question bank.');
        abort_if(($validated['questions_per_attempt'] ?? 0) > $selectedQuestions->count(), 422, 'Questions per attempt cannot exceed the number of questions in this exam.');
        $totalMarks = $selectedQuestions->sum('marks');
        if ($validated['pass_marks'] > $totalMarks) {
            return back()->withErrors(['pass_marks' => 'Pass marks cannot exceed the total marks.'])->withInput();
        }

        DB::transaction(function () use ($batch, $validated, $selectedQuestions) {
            $exam = $batch->exams()->create([
                'teacher_id' => Auth::id(),
                'title' => $validated['title'],
                'instructions' => $validated['instructions'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'duration_minutes' => $validated['duration_minutes'],
                'pass_marks' => $validated['pass_marks'],
                'questions_per_attempt' => $validated['questions_per_attempt'] ?? null,
                'status' => 'published',
            ]);

            foreach ($selectedQuestions as $position => $question) {
                $exam->questions()->create([...$question, 'position' => $position + 1]);
            }
        });

        return back()->with('success', 'Exam published successfully.');
    }

    public function questionBank(Batch $batch): View
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $query = QuestionBankQuestion::where('teacher_id', Auth::id());
        if (request('search')) {
            $term = trim(request('search'));
            $query->where(fn ($builder) => $builder->where('question', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%"));
        }
        if (request('category')) $query->where('category', request('category'));
        if (request('difficulty')) $query->where('difficulty', request('difficulty'));
        $questions = $query->latest()->paginate(25)->withQueryString();
        $categories = QuestionBankQuestion::where('teacher_id', Auth::id())->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('teacher.exams.question-bank', compact('batch', 'questions', 'categories'));
    }

    public function storeBankQuestion(Batch $batch, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        QuestionBankQuestion::create([...$validated, 'teacher_id' => Auth::id()]);

        return back()->with('success', 'Question added to your question bank.');
    }


    public function updateBankQuestion(Batch $batch, QuestionBankQuestion $question, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        abort_unless($question->teacher_id === Auth::id(), 403);
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $question->update($validated);

        return back()->with('success', 'Question updated successfully.');
    }

    public function destroyBankQuestion(Batch $batch, QuestionBankQuestion $question): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        abort_unless($question->teacher_id === Auth::id(), 403);
        abort_if($question->examQuestions()->exists(), 409, 'This question is already used in an exam and cannot be deleted.');

        $question->delete();

        return back()->with('success', 'Question deleted successfully.');
    }

    public function teacherResults(Exam $exam): View
    {
        abort_unless(Auth::user()->isAdmin() || $exam->batch->teacher_id === Auth::id(), 403);

        $attempts = $exam->attempts()
            ->with('student:id,name,email')
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->paginate(30);

        return view('teacher.exams.results', compact('exam', 'attempts'));
    }

    public function studentExams(): View
    {
        abort_unless(Auth::user()->isStudent(), 403);
        $exams = Exam::with('batch.course')
            ->where('status', 'published')
            ->whereHas('batch.enrollments', fn ($query) => $query
                ->where('user_id', Auth::id())
                ->where('status', 'active'))
            ->orderBy('starts_at')
            ->paginate(20);

        return view('exams.index', compact('exams'));
    }

    public function studentIndex(Exam $exam): View
    {
        $this->ensureEnrolled($exam);
        $attempt = $exam->attempts()->where('student_id', Auth::id())->with('attemptQuestions.question')->first();
        $exam->load('batch.course');

        return view('exams.show', compact('exam', 'attempt'));
    }

    public function start(Exam $exam): RedirectResponse
    {
        $this->ensureEnrolled($exam);
        abort_unless($exam->status === 'published' && now()->between($exam->starts_at, $exam->ends_at), 403);
        abort_unless($exam->questions()->exists(), 403);

        $attempt = DB::transaction(function () use ($exam) {
            $attempt = ExamAttempt::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => Auth::id()],
                ['started_at' => now(), 'status' => 'in_progress']
            );

            // Freeze a shuffled question order for this attempt exactly once.
            // Refreshing or reopening the exam will never reshuffle a student's paper.
            if ($attempt->wasRecentlyCreated || ! $attempt->attemptQuestions()->exists()) {
                $questionQuery = $exam->questions()->inRandomOrder();
                if ($exam->questions_per_attempt) {
                    $questionQuery->limit($exam->questions_per_attempt);
                }
                $questionIds = $questionQuery->pluck('id')->values();
                $attempt->attemptQuestions()->createMany(
                    $questionIds->map(fn ($questionId, $index) => [
                        'exam_question_id' => $questionId,
                        'position' => $index + 1,
                    ])->all()
                );
            }

            return $attempt;
        });

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.exams.show', $exam)->with('info', 'You have already submitted this exam.');
        }

        return redirect()->route('student.exams.show', $exam);
    }

    public function submit(ExamAttempt $attempt, Request $request): RedirectResponse
    {
        abort_unless($attempt->student_id === Auth::id(), 403);
        abort_unless($attempt->status === 'in_progress', 403);

        $exam = $attempt->exam()->firstOrFail();
        $this->ensureEnrolled($exam);
        $attemptQuestions = $attempt->attemptQuestions()->with('question')->orderBy('position')->get();
        abort_if($attemptQuestions->isEmpty(), 422, 'No questions are assigned to this attempt.');
        $deadline = min($exam->ends_at->getTimestamp(), $attempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp());
        abort_if(now()->getTimestamp() > $deadline, 403, 'The exam submission window has closed.');

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $questionIds = $attemptQuestions->pluck('exam_question_id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_keys($validated['answers']);
        abort_unless(count(array_diff($submittedIds, $questionIds)) === 0, 422);

        $score = 0;
        DB::transaction(function () use ($attemptQuestions, $attempt, $validated, &$score) {
            foreach ($attemptQuestions as $attemptQuestion) {
                $question = $attemptQuestion->question;
                if (! $question) {
                    continue;
                }
                if (! array_key_exists((string) $question->id, $validated['answers'])) {
                    continue;
                }
                $selected = (int) $validated['answers'][(string) $question->id];
                $correct = $selected === $question->correct_option;
                $awarded = $correct ? $question->marks : 0;
                $score += $awarded;
                ExamAnswer::create([
                    'exam_attempt_id' => $attempt->id,
                    'exam_question_id' => $question->id,
                    'selected_option' => $selected,
                    'is_correct' => $correct,
                    'marks_awarded' => $awarded,
                ]);
            }
            $attempt->update(['submitted_at' => now(), 'score' => $score, 'status' => 'submitted']);
        });

        return redirect()->route('student.exams.show', $exam)->with('success', 'Exam submitted. Your score is '.$score.'.');
    }

    private function ensureEnrolled(Exam $exam): void
    {
        abort_unless(Auth::user()->isStudent(), 403);
        abort_unless(Enrollment::where('batch_id', $exam->batch_id)->where('user_id', Auth::id())->where('status', 'active')->exists(), 403);
    }
}
