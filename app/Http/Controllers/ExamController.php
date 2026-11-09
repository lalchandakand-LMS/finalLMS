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
        $bankQuestions = QuestionBankQuestion::where('teacher_id', $batch->teacher_id)->latest()->get();
        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.exams.index', compact('batch', 'exams', 'bankQuestions', 'isAdmin'));
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
            'question_bank_ids' => ['nullable', 'array', 'min:1', 'max:100'],
            'question_bank_ids.*' => ['integer', 'distinct', 'exists:question_bank_questions,id'],
            'random_question_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.question' => ['required', 'string', 'max:3000'],
            'questions.*.options' => ['required', 'array', 'size:4'],
            'questions.*.options.*' => ['required', 'string', 'max:500', 'distinct'],
            'questions.*.correct_option' => ['required', 'integer', 'between:0,3'],
            'questions.*.marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $selectedQuestions = collect($validated['questions'] ?? []);
        if (! empty($validated['question_bank_ids'])) {
            $bankQuestions = QuestionBankQuestion::query()
                ->whereIn('id', $validated['question_bank_ids'])
                ->where('teacher_id', $batch->teacher_id)
                ->inRandomOrder()
                ->get();

            abort_unless($bankQuestions->count() === count($validated['question_bank_ids']), 403, 'One or more selected questions do not belong to you.');
            $selectedCount = $validated['random_question_count'] ?? $bankQuestions->count();
            abort_if($selectedCount > $bankQuestions->count(), 422, 'Random question count exceeds the selected bank questions.');

            $bankSelections = $bankQuestions->shuffle()->take($selectedCount)->map(fn ($question) => [
                'question_bank_question_id' => $question->id,
                'question' => $question->question,
                'options' => $question->options,
                'correct_option' => $question->correct_option,
                'marks' => $question->marks,
            ]);
            // Preserve manually authored questions when a teacher combines both sources.
            $selectedQuestions = $selectedQuestions->concat($bankSelections)->values();
        }

        abort_if($selectedQuestions->isEmpty(), 422, 'Add questions or select questions from your question bank.');
        abort_if(($validated['questions_per_attempt'] ?? 0) > $selectedQuestions->count(), 422, 'Questions per attempt cannot exceed the number of questions in this exam.');
        $totalMarks = $selectedQuestions->sum('marks');
        if ($validated['pass_marks'] > $totalMarks) {
            return back()->withErrors(['pass_marks' => 'Pass marks cannot exceed the total marks.'])->withInput();
        }

        DB::transaction(function () use ($batch, $validated, $selectedQuestions) {
            $exam = $batch->exams()->create([
                'teacher_id' => $batch->teacher_id,
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
        $filters = request()->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
        ]);
        $ownerId = $batch->teacher_id;
        $query = QuestionBankQuestion::where('teacher_id', $ownerId)
            ->when($filters['subject'] ?? null, fn ($builder, string $subject) => $builder->where('subject', $subject))
            ->when($filters['category'] ?? null, fn ($builder, string $category) => $builder->where('category', $category))
            ->when($filters['difficulty'] ?? null, fn ($builder, string $difficulty) => $builder->where('difficulty', $difficulty))
            ->when($filters['search'] ?? null, function ($builder, string $term): void {
                $term = trim($term);
                if ($term !== '') {
                    $builder->where(fn ($nested) => $nested->where('question', 'like', '%'.$term.'%')
                        ->orWhere('subject', 'like', '%'.$term.'%'));
                }
            });
        $questions = $query->latest()->paginate(25)->withQueryString();
        $subjects = QuestionBankQuestion::where('teacher_id', $ownerId)
            ->whereNotNull('subject')->where('subject', '<>', '')
            ->select('subject')->distinct()->orderBy('subject')->pluck('subject');
        $categories = QuestionBankQuestion::where('teacher_id', $ownerId)
            ->whereNotNull('category')->where('category', '<>', '')
            ->select('category')->distinct()->orderBy('category')->pluck('category');

        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.exams.question-bank', compact('batch', 'questions', 'subjects', 'categories', 'isAdmin'));
    }

    public function storeBankQuestion(Batch $batch, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['sometimes', 'in:easy,medium,hard'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:500', 'distinct'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        QuestionBankQuestion::create([...$validated, 'teacher_id' => $batch->teacher_id]);

        return back()->with('success', 'Question added to your question bank.');
    }


    public function updateBankQuestion(Batch $batch, QuestionBankQuestion $question, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        abort_unless((int) $question->teacher_id === (int) $batch->teacher_id, 403);
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['sometimes', 'in:easy,medium,hard'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:500', 'distinct'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $question->update($validated);

        return back()->with('success', 'Question updated successfully.');
    }

    public function destroyBankQuestion(Batch $batch, QuestionBankQuestion $question): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        abort_unless((int) $question->teacher_id === (int) $batch->teacher_id, 403);
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

        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.exams.results', compact('exam', 'attempts', 'isAdmin'));
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
        // Draft exams must not be discoverable through a guessed URL.
        abort_unless($exam->status === 'published', 404);
        $attempt = $exam->attempts()->where('student_id', Auth::id())->with('attemptQuestions.question')->first();
        $exam->load('batch.course');

        return view('exams.show', compact('exam', 'attempt'));
    }

    public function start(Exam $exam): RedirectResponse
    {
        $this->ensureEnrolled($exam);

        $attempt = DB::transaction(function () use ($exam): ExamAttempt {
            // Lock the exam row so two simultaneous start requests cannot create
            // competing attempts or different question sets for one student.
            $lockedExam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $this->ensureEnrolled($lockedExam);

            abort_unless(
                $lockedExam->status === 'published'
                    && now()->between($lockedExam->starts_at, $lockedExam->ends_at),
                403
            );
            abort_unless($lockedExam->questions()->exists(), 403);

            $attempt = ExamAttempt::query()
                ->where('exam_id', $lockedExam->id)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $attempt = ExamAttempt::create([
                    'exam_id' => $lockedExam->id,
                    'student_id' => Auth::id(),
                    'started_at' => now(),
                    'status' => 'in_progress',
                ]);
            }

            // Freeze a shuffled question order for this attempt exactly once.
            if ($attempt->status === 'in_progress' && ! $attempt->attemptQuestions()->exists()) {
                $questionQuery = $lockedExam->questions()->reorder()->inRandomOrder();
                if ($lockedExam->questions_per_attempt) {
                    $questionQuery->limit($lockedExam->questions_per_attempt);
                }

                $questionIds = $questionQuery->pluck('id')->values();
                abort_if($questionIds->isEmpty(), 422, 'No questions are available for this attempt.');

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
            return redirect()->route('student.exams.show', $exam)
                ->with('info', 'You have already submitted this exam.');
        }

        return redirect()->route('student.exams.show', $exam);
    }

    public function submit(ExamAttempt $attempt, Request $request): RedirectResponse
    {
        $score = DB::transaction(function () use ($attempt, $request): int {
            $lockedAttempt = ExamAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            abort_unless((int) $lockedAttempt->student_id === (int) Auth::id(), 403);
            abort_unless($lockedAttempt->status === 'in_progress', 403);

            $exam = $lockedAttempt->exam()->firstOrFail();
            $this->ensureEnrolled($exam);

            $attemptQuestions = $lockedAttempt->attemptQuestions()
                ->with('question')
                ->orderBy('position')
                ->get();

            abort_if($attemptQuestions->isEmpty(), 422, 'No questions are assigned to this attempt.');

            $deadline = min(
                $exam->ends_at->getTimestamp(),
                $lockedAttempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
            );
            abort_if(now()->getTimestamp() > $deadline, 403, 'The exam submission window has closed.');

            $validated = $request->validate([
                'answers' => ['required', 'array'],
                'answers.*' => ['required', 'integer', 'between:0,3'],
            ]);

            $questionIds = $attemptQuestions
                ->pluck('exam_question_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $submittedIds = array_map('strval', array_keys($validated['answers']));
            abort_unless(count(array_diff($submittedIds, $questionIds)) === 0, 422);

            $totalScore = 0;

            foreach ($attemptQuestions as $attemptQuestion) {
                $question = $attemptQuestion->question;
                if (! $question || ! array_key_exists((string) $question->id, $validated['answers'])) {
                    continue;
                }

                $selected = (int) $validated['answers'][(string) $question->id];
                $correct = $selected === $question->correct_option;
                $awarded = $correct ? $question->marks : 0;
                $totalScore += $awarded;

                ExamAnswer::create([
                    'exam_attempt_id' => $lockedAttempt->id,
                    'exam_question_id' => $question->id,
                    'selected_option' => $selected,
                    'is_correct' => $correct,
                    'marks_awarded' => $awarded,
                ]);
            }

            $lockedAttempt->update([
                'submitted_at' => now(),
                'score' => $totalScore,
                'status' => 'submitted',
            ]);

            return $totalScore;
        });

        return redirect()->route('student.exams.show', $attempt->exam)
            ->with('success', 'Exam submitted. Your score is '.$score.'.');
    }

    private function ensureEnrolled(Exam $exam): void
    {
        abort_unless(Auth::user()->isStudent(), 403);
        abort_unless(Enrollment::where('batch_id', $exam->batch_id)->where('user_id', Auth::id())->where('status', 'active')->exists(), 403);
    }
}
