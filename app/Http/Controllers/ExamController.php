<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\AcademicLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAnswer;
use App\Models\ExamResultReview;
use App\Models\Enrollment;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionSet;
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
        $questionSetQuery = QuestionSet::query()->published()->withCount('questions');
        if (! $isAdmin) {
            $questionSetQuery->where(function ($query) use ($batch): void {
                $query->where('created_by', $batch->teacher_id)
                    ->orWhereHas('courses', fn ($courses) => $courses->whereKey($batch->course_id));
            });
        }
        $questionSets = $questionSetQuery->latest()->get();
        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.exams.index', compact('batch', 'exams', 'bankQuestions', 'questionSets', 'isAdmin'));
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
            'grading_rules' => ['nullable', 'array'],
            'grading_rules.negative_marking_enabled' => ['nullable', 'boolean'],
            'grading_rules.negative_marking_multiplier' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'grading_rules.score_floor_zero' => ['nullable', 'boolean'],
            'grading_rules.short_answer_case_sensitive' => ['nullable', 'boolean'],
            'allow_result_review' => ['nullable', 'boolean'],
            'review_until' => ['nullable', 'date', 'after:ends_at'],
            'questions' => ['nullable', 'array', 'min:1', 'max:100'],
            'question_bank_ids' => ['nullable', 'array', 'min:1', 'max:100'],
            'question_bank_ids.*' => ['integer', 'distinct', 'exists:question_bank_questions,id'],
            'question_set_ids' => ['nullable', 'array', 'max:30'],
            'question_set_ids.*' => ['integer', 'distinct', 'exists:question_sets,id'],
            'random_question_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.type' => ['nullable', 'in:mcq,true_false,short_answer,written'],
            'questions.*.question' => ['required', 'string', 'max:3000'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*' => ['required', 'string', 'max:500', 'distinct'],
            'questions.*.correct_option' => ['nullable', 'integer', 'between:0,3'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:2000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:3000'],
            'questions.*.grading_rubric' => ['nullable', 'array', 'max:20'],
            'questions.*.marks' => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.negative_marks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
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
                'negative_marks' => $question->negative_marks,
                'type' => $question->type ?: 'mcq',
                'correct_answer' => $question->correct_answer,
                'explanation' => $question->explanation,
                'grading_rubric' => $question->grading_rubric,
            ]);
            $selectedQuestions = $selectedQuestions->concat($bankSelections)->values();
        }

        if (! empty($validated['question_set_ids'])) {
            $sets = QuestionSet::query()->published()->with('questions')
                ->whereIn('id', $validated['question_set_ids'])->get();
            abort_unless($sets->count() === count($validated['question_set_ids']), 422, 'One or more question sets are unavailable.');
            foreach ($sets as $set) {
                $isOwned = (int) $set->created_by === (int) $batch->teacher_id;
                $isAttached = $set->courses()->whereKey($batch->course_id)->exists();
                abort_unless(Auth::user()->isAdmin() || $isOwned || $isAttached, 403, 'You cannot use this question set.');
                foreach ($set->questions as $setQuestion) {
                    $selectedQuestions->push([
                        'question_set_question_id' => $setQuestion->id,
                        'type' => $setQuestion->type ?: 'mcq',
                        'question' => $setQuestion->question,
                        'options' => $setQuestion->options,
                        'correct_option' => $setQuestion->correct_option,
                        'correct_answer' => $setQuestion->correct_answer,
                        'explanation' => $setQuestion->explanation,
                        'grading_rubric' => null,
                        'marks' => $setQuestion->marks,
                        'negative_marks' => 0,
                    ]);
                }
            }
        }

        abort_if($selectedQuestions->isEmpty(), 422, 'Add questions or select questions from your question bank.');
        $selectedQuestions = $selectedQuestions->values()->map(function (array $question): array {
            $type = $question['type'] ?? 'mcq';
            $question['type'] = $type;
            if ($type === 'true_false') {
                $question['options'] = ['True', 'False'];
                $question['correct_option'] = (int) ($question['correct_option'] ?? 0);
                abort_unless(in_array($question['correct_option'], [0, 1], true), 422, 'True/False answers must be True or False.');
            } elseif ($type === 'mcq') {
                abort_unless(count($question['options'] ?? []) === 4 && isset($question['correct_option']), 422, 'MCQ questions require four options and one correct answer.');
            } elseif ($type === 'short_answer') {
                abort_unless(trim((string) ($question['correct_answer'] ?? '')) !== '', 422, 'Short-answer questions require an expected answer.');
                $question['options'] = null;
                $question['correct_option'] = null;
            } else {
                $question['options'] = null;
                $question['correct_option'] = null;
                $question['correct_answer'] = null;
            }
            return $question;
        });
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
                'allow_result_review' => (bool) ($validated['allow_result_review'] ?? false),
                'review_until' => $validated['review_until'] ?? null,
                'grading_rules' => [
                    'negative_marking_enabled' => (bool) ($validated['grading_rules']['negative_marking_enabled'] ?? true),
                    'negative_marking_multiplier' => (float) ($validated['grading_rules']['negative_marking_multiplier'] ?? 1),
                    'score_floor_zero' => (bool) ($validated['grading_rules']['score_floor_zero'] ?? true),
                    'short_answer_case_sensitive' => (bool) ($validated['grading_rules']['short_answer_case_sensitive'] ?? false),
                ],
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
            'type' => ['nullable', 'in:mcq,true_false,short_answer,written'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['required', 'string', 'max:500', 'distinct'],
            'correct_option' => ['nullable', 'integer', 'between:0,3'],
            'correct_answer' => ['nullable', 'string', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'grading_rubric' => ['nullable', 'array', 'max:20'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:40'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
            'negative_marks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $validated = $this->normalizeBankQuestion($validated);
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
            'type' => ['nullable', 'in:mcq,true_false,short_answer,written'],
            'question' => ['required', 'string', 'max:3000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['required', 'string', 'max:500', 'distinct'],
            'correct_option' => ['nullable', 'integer', 'between:0,3'],
            'correct_answer' => ['nullable', 'string', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'grading_rubric' => ['nullable', 'array', 'max:20'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:40'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
            'negative_marks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $question->update($this->normalizeBankQuestion($validated));

        return back()->with('success', 'Question updated successfully.');
    }

    private function normalizeBankQuestion(array $question): array
    {
        $type = $question['type'] ?? 'mcq';
        $question['type'] = $type;
        if ($type === 'true_false') {
            $question['options'] = ['True', 'False'];
            $question['correct_option'] = (int) ($question['correct_option'] ?? 0);
            abort_unless(in_array($question['correct_option'], [0, 1], true), 422);
        } elseif ($type === 'mcq') {
            abort_unless(count($question['options'] ?? []) === 4 && isset($question['correct_option']), 422);
        } elseif ($type === 'short_answer') {
            abort_unless(trim((string) ($question['correct_answer'] ?? '')) !== '', 422);
            $question['options'] = null;
            $question['correct_option'] = null;
        } else {
            $question['options'] = null;
            $question['correct_option'] = null;
            $question['correct_answer'] = null;
        }
        return $question;
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
            ->with(['student:id,name,email', 'answers.question'])
            ->whereIn('status', ['submitted', 'awaiting_manual'])
            ->latest('submitted_at')
            ->paginate(30);

        $summary = $exam->attempts()
            ->where('status', 'submitted')
            ->selectRaw(
                'COUNT(*) as total_submitted, AVG(score) as average_score, MAX(score) as highest_score, MIN(score) as lowest_score, SUM(CASE WHEN score >= ? THEN 1 ELSE 0 END) as passed_count',
                [$exam->pass_marks]
            )
            ->first();

        $totalSubmitted = (int) ($summary->total_submitted ?? 0);
        $passRate = $totalSubmitted > 0 ? round(((int) $summary->passed_count / $totalSubmitted) * 100, 1) : 0;
        $questionAnalytics = DB::table('exam_questions as questions')
            ->leftJoin('exam_answers as answers', 'answers.exam_question_id', '=', 'questions.id')
            ->leftJoin('exam_attempts as attempts', function ($join) use ($exam): void {
                $join->on('attempts.id', '=', 'answers.exam_attempt_id')
                    ->where('attempts.exam_id', '=', $exam->id)
                    ->where('attempts.status', '=', 'submitted');
            })
            ->where('questions.exam_id', $exam->id)
            ->groupBy('questions.id', 'questions.question', 'questions.position')
            ->orderBy('questions.position')
            ->selectRaw('questions.id, questions.question, questions.position, COUNT(attempts.id) as answered_count, SUM(CASE WHEN attempts.id IS NOT NULL AND answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_count')
            ->get()
            ->map(function ($row) {
                $row->accuracy = (int) $row->answered_count > 0
                    ? round(((int) $row->correct_count / (int) $row->answered_count) * 100, 1)
                    : 0;
                return $row;
            });

        $reviews = ExamResultReview::query()->with('student')->whereHas('attempt', fn ($query) => $query->where('exam_id', $exam->id))->latest()->get();
        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.exams.results', compact('exam', 'attempts', 'isAdmin', 'summary', 'totalSubmitted', 'passRate', 'questionAnalytics', 'reviews'));
    }

    public function studentExams(Request $request): View
    {
        abort_unless(Auth::user()->isStudent(), 403);
        $filters = $request->validate([
            'academic_level_id' => ['nullable', 'integer', 'exists:academic_levels,id'],
        ]);
        $exams = Exam::with('batch.course.academicLevel')
            ->where('status', 'published')
            ->whereHas('batch.enrollments', fn ($query) => $query
                ->where('user_id', Auth::id())
                ->where('status', 'active'))
            ->when($filters['academic_level_id'] ?? null, fn ($query, $levelId) => $query
                ->whereHas('batch.course', fn ($courses) => $courses->where('academic_level_id', $levelId)))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();
        $academicLevels = AcademicLevel::query()
            ->where('is_active', true)
            ->whereHas('courses.batches.exams', fn ($query) => $query
                ->where('status', 'published')
                ->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                    ->where('user_id', Auth::id())
                    ->where('status', 'active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('exams.index', compact('exams', 'academicLevels', 'filters'));
    }

    public function studentIndex(Exam $exam): View
    {
        $this->ensureEnrolled($exam);
        abort_unless($exam->status === 'published', 404);
        $attempt = $exam->attempts()->where('student_id', Auth::id())->with(['attemptQuestions.question', 'answers'])->first();
        if ($attempt && $attempt->status === 'in_progress') {
            app(\App\Services\ExamAttemptFinalizer::class)->autoSubmitExpired((int) $attempt->id);
            $attempt->refresh()->load(['attemptQuestions.question', 'answers']);
        }
        $review = $attempt?->resultReview;
        $exam->load('batch.course');

        return view('exams.show', compact('exam', 'attempt', 'review'));
    }

    public function start(Exam $exam): RedirectResponse
    {
        $this->ensureEnrolled($exam);

        $attempt = DB::transaction(function () use ($exam): ExamAttempt {
            // Serialize concurrent starts so one student receives one frozen paper.
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
        DB::transaction(function () use ($attempt, $request): void {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless((int) $lockedAttempt->student_id === (int) Auth::id(), 403);
            abort_unless($lockedAttempt->status === 'in_progress', 403);
            $exam = $lockedAttempt->exam()->firstOrFail();
            $this->ensureEnrolled($exam);
            $attemptQuestions = $lockedAttempt->attemptQuestions()->with('question')->orderBy('position')->get();
            abort_if($attemptQuestions->isEmpty(), 422, 'No questions are assigned to this attempt.');

            $deadline = min($exam->ends_at->getTimestamp(), $lockedAttempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp());
            abort_if(now()->getTimestamp() > $deadline + 30, 403, 'The exam submission window has closed.');
            $validated = $request->validate(['answers' => ['sometimes', 'array', 'max:100']]);
            $submitted = $validated['answers'] ?? [];
            $questionIds = $attemptQuestions->pluck('exam_question_id')->map(fn ($id) => (string) $id)->all();
            abort_unless(count(array_diff(array_map('strval', array_keys($submitted)), $questionIds)) === 0, 422);

            $saved = $lockedAttempt->answers()->get()->keyBy('exam_question_id');
            $rules = $exam->grading_rules ?? [];
            $total = 0;
            $manualPending = false;
            foreach ($attemptQuestions as $attemptQuestion) {
                $question = $attemptQuestion->question;
                if (! $question) continue;
                $answer = $saved[$question->id] ?? null;
                $value = $submitted[(string) $question->id] ?? $submitted[$question->id] ?? null;
                if ($value !== null) {
                    if (in_array($question->type ?: 'mcq', ['mcq', 'true_false'], true)) {
                        abort_unless(filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0 && (int) $value < count($question->options ?? []), 422);
                        $answer = $lockedAttempt->answers()->updateOrCreate(
                            ['exam_question_id' => $question->id],
                            ['selected_option' => (int) $value, 'answer_text' => null, 'is_correct' => false, 'marks_awarded' => 0]
                        );
                    } else {
                        abort_unless(is_string($value), 422);
                        abort_if(mb_strlen($value) > 10000, 422);
                        $answer = $lockedAttempt->answers()->updateOrCreate(
                            ['exam_question_id' => $question->id],
                            ['selected_option' => null, 'answer_text' => trim($value), 'is_correct' => false, 'marks_awarded' => 0]
                        );
                    }
                }
                if (! $answer) continue;

                $type = $question->type ?: 'mcq';
                if ($type === 'written') {
                    $manualPending = true;
                    continue;
                }
                if ($type === 'short_answer') {
                    $expected = trim((string) $question->correct_answer);
                    $actual = trim((string) $answer->answer_text);
                    if (! ($rules['short_answer_case_sensitive'] ?? false)) {
                        $expected = mb_strtolower(preg_replace('/\\s+/u', ' ', $expected));
                        $actual = mb_strtolower(preg_replace('/\\s+/u', ' ', $actual));
                    }
                    $correct = $expected !== '' && $actual === $expected;
                } else {
                    $correct = $answer->selected_option !== null && (int) $answer->selected_option === (int) $question->correct_option;
                }

                $penaltyEnabled = (bool) ($rules['negative_marking_enabled'] ?? true);
                $multiplier = (float) ($rules['negative_marking_multiplier'] ?? 1);
                $awarded = $correct ? (int) $question->marks : ($penaltyEnabled ? -1 * (int) ($question->negative_marks ?? 0) * $multiplier : 0);
                $answer->update(['is_correct' => $correct, 'marks_awarded' => (int) round($awarded)]);
                $total += (int) round($awarded);
            }

            $floor = (bool) ($rules['score_floor_zero'] ?? true);
            $lockedAttempt->update([
                'submitted_at' => now(),
                'score' => $floor ? max(0, $total) : $total,
                'status' => $manualPending ? 'awaiting_manual' : 'submitted',
            ]);
        });

        $attempt->refresh();
        return redirect()->route('student.exams.show', $attempt->exam)
            ->with('success', $attempt->status === 'awaiting_manual'
                ? 'Exam submitted. Written answers are awaiting teacher review.'
                : 'Exam submitted successfully.');
    }

    public function gradeAnswer(ExamAnswer $answer, Request $request): RedirectResponse
    {
        $exam = $answer->attempt->exam()->with('batch')->firstOrFail();
        abort_unless(Auth::user()->isAdmin() || (int) $exam->batch->teacher_id === (int) Auth::id(), 403);
        abort_unless($answer->question?->type === 'written', 422);
        abort_unless($answer->attempt->status === 'awaiting_manual', 409);
        $validated = $request->validate([
            'marks_awarded' => ['required', 'integer', 'min:0', 'max:'.$answer->question->marks],
            'teacher_feedback' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($answer, $validated, $request): void {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($answer->exam_attempt_id);
            abort_unless($lockedAttempt->status === 'awaiting_manual', 409);
            $lockedAnswer = $lockedAttempt->answers()->lockForUpdate()->findOrFail($answer->id);
            $lockedAnswer->update([
                'marks_awarded' => $validated['marks_awarded'],
                'teacher_feedback' => $validated['teacher_feedback'] ?? null,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);

            $pending = $lockedAttempt->answers()->whereHas('question', fn ($query) => $query->where('type', 'written'))
                ->whereNull('graded_at')->exists();
            if (! $pending) {
                $lockedAttempt->update([
                    'score' => (int) $lockedAttempt->answers()->sum('marks_awarded'),
                    'status' => 'submitted',
                    'graded_by' => $request->user()->id,
                    'graded_at' => now(),
                ]);
            }
        });

        return back()->with('success', __('Written answer graded successfully.'));
    }

    public function requestResultReview(ExamAttempt $attempt, Request $request): RedirectResponse
    {
        abort_unless((int) $attempt->student_id === (int) Auth::id(), 403);
        $exam = $attempt->exam()->firstOrFail();
        abort_unless($attempt->status === 'submitted' && $exam->result_status === 'published' && $exam->allow_result_review, 403);
        abort_if($exam->review_until && now()->greaterThan($exam->review_until), 409, __('The result review period has closed.'));
        abort_if($attempt->resultReview()->exists(), 409, __('A review request has already been submitted for this attempt.'));
        $validated = $request->validate(['message' => ['required', 'string', 'min:10', 'max:2000']]);
        $attempt->resultReview()->create([
            'student_id' => Auth::id(),
            'message' => $validated['message'],
            'status' => 'pending',
        ]);
        return back()->with('success', __('Your result review request has been submitted.'));
    }

    public function respondToResultReview(ExamResultReview $review, Request $request): RedirectResponse
    {
        $exam = $review->attempt()->with('exam.batch')->firstOrFail()->exam;
        abort_unless(Auth::user()->isAdmin() || (int) $exam->batch->teacher_id === (int) Auth::id(), 403);
        abort_unless($review->status === 'pending', 409);
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'teacher_response' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $review->update([
            'status' => $validated['status'],
            'teacher_response' => $validated['teacher_response'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
        return back()->with('success', __('Result review request updated.'));
    }

    public function publishResults(Exam $exam): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin() || (int) $exam->batch->teacher_id === (int) Auth::id(), 403);
        abort_if($exam->attempts()->where('status', 'awaiting_manual')->exists(), 409, __('Grade all written answers before publishing results.'));
        $exam->update(['result_status' => 'published', 'results_published_at' => now()]);
        return back()->with('success', __('Exam results are now published to students.'));
    }

    public function resultSheet(Exam $exam): View
    {
        abort_unless(Auth::user()->isAdmin() || (int) $exam->batch->teacher_id === (int) Auth::id(), 403);
        $attempts = $exam->attempts()->with('student:id,name,email')
            ->where('status', 'submitted')->orderByDesc('score')->orderBy('submitted_at')->get();
        $attempts = $attempts->values()->map(function ($attempt, $index) use ($exam) {
            $attempt->merit_position = $index + 1;
            $attempt->result_label = $attempt->score >= $exam->pass_marks ? 'Passed' : 'Not passed';
            return $attempt;
        });
        return view('teacher.exams.result-sheet', compact('exam', 'attempts'));
    }

    private function ensureEnrolled(Exam $exam): void
    {
        abort_unless(Auth::user()->isStudent(), 403);
        abort_unless(Enrollment::where('batch_id', $exam->batch_id)->where('user_id', Auth::id())->where('status', 'active')->exists(), 403);
    }
}
