<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\QuestionSetAttemptQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionSetAttemptController extends Controller
{
    public function show(Course $course, QuestionSet $questionSet): View
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($questionSet->status === 'published', 404);
        $this->assertCourseAccess($course, $questionSet, $student->id);

        $questionSet->loadCount('questions');
        $pivot = $course->questionSets()->whereKey($questionSet->id)->firstOrFail()->pivot;
        abort_if($pivot->available_from && now()->lt($pivot->available_from), 404);

        $attempts = QuestionSetAttempt::query()
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->where('student_id', $student->id)
            ->latest('attempt_number')
            ->get();

        $activeAttempt = $attempts->firstWhere('status', 'in_progress');

        return view('student.question-sets.show', compact('course', 'questionSet', 'pivot', 'attempts', 'activeAttempt'));
    }

    public function start(Request $request, Course $course, QuestionSet $questionSet): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        $this->assertCourseAccess($course, $questionSet, $student->id);

        $attempt = DB::transaction(function () use ($course, $questionSet, $student) {
            $lockedSet = QuestionSet::query()->whereKey($questionSet->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedSet->status === 'published', 404);

            $pivot = $course->questionSets()->whereKey($lockedSet->id)->firstOrFail()->pivot;
            abort_if($pivot->available_from && now()->lt($pivot->available_from), 403, __('This quiz is not available yet.'));

            $existing = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $lockedSet->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $attemptCount = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $lockedSet->id)
                ->where('student_id', $student->id)
                ->count();
            if ($pivot->max_attempts && $attemptCount >= $pivot->max_attempts) {
                throw ValidationException::withMessages(['attempt' => __('You have used all allowed attempts for this quiz.')]);
            }

            $sourceQuestions = $lockedSet->questions()->get();
            if ($sourceQuestions->isEmpty()) {
                throw ValidationException::withMessages(['attempt' => __('This quiz does not contain any questions yet.')]);
            }
            if ($pivot->randomize_questions) {
                $sourceQuestions = $sourceQuestions->shuffle();
            }
            if ($pivot->questions_per_attempt) {
                $sourceQuestions = $sourceQuestions->take((int) $pivot->questions_per_attempt);
            }

            $startedAt = now();
            $deadlineAt = $pivot->time_limit_minutes
                ? $startedAt->copy()->addMinutes((int) $pivot->time_limit_minutes)
                : null;
            $attempt = QuestionSetAttempt::create([
                'question_set_id' => $lockedSet->id,
                'course_id' => $course->id,
                'student_id' => $student->id,
                'attempt_number' => $attemptCount + 1,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'deadline_at' => $deadlineAt,
                'max_score' => $sourceQuestions->sum('marks'),
            ]);

            foreach ($sourceQuestions->values() as $index => $question) {
                $attempt->questions()->create([
                    'question_set_question_id' => $question->id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'marks' => $question->marks,
                    'position' => ($index + 1) * 10,
                ]);
            }

            return $attempt;
        });

        return redirect()->route('student.question-sets.attempts.show', [$course, $questionSet, $attempt]);
    }

    public function attempt(Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): View
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($attempt->course_id === $course->id
            && $attempt->question_set_id === $questionSet->id
            && $attempt->student_id === $student->id, 404);
        $this->assertCourseAccess($course, $questionSet, $student->id);
        abort_unless($attempt->status === 'in_progress', 404);

        $attempt->load(['questions' => fn ($query) => $query->select(['id', 'question_set_attempt_id', 'type', 'question', 'options', 'marks', 'position'])]);

        return view('student.question-sets.attempt', compact('course', 'questionSet', 'attempt'));
    }

    public function submit(Request $request, Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($attempt->course_id === $course->id
            && $attempt->question_set_id === $questionSet->id
            && $attempt->student_id === $student->id, 404);
        $this->assertCourseAccess($course, $questionSet, $student->id);

        DB::transaction(function () use ($request, $attempt) {
            $lockedAttempt = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($lockedAttempt->status !== 'in_progress') {
                throw ValidationException::withMessages(['attempt' => __('This attempt has already been submitted.')]);
            }

            $expired = $lockedAttempt->deadline_at && now()->greaterThan($lockedAttempt->deadline_at);
            $questions = $lockedAttempt->questions()->get();
            $answers = $expired ? [] : (array) $request->input('answers', []);
            $allowedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
            if (array_diff(array_map('strval', array_keys($answers)), $allowedIds)) {
                throw ValidationException::withMessages(['answers' => __('Invalid answer data was submitted.')]);
            }

            $autoScore = 0;
            $hasManualQuestions = false;
            foreach ($questions as $question) {
                $answer = $answers[$question->id] ?? null;
                $isCorrect = null;
                $selectedOption = null;
                $answerText = null;
                $marksAwarded = 0;

                if ($question->type === 'mcq' || $question->type === 'true_false') {
                    if ($answer !== null && $answer !== '') {
                        if (filter_var($answer, FILTER_VALIDATE_INT) === false
                            || (int) $answer < 0
                            || (int) $answer >= count($question->options ?? [])) {
                            throw ValidationException::withMessages(["answers.{$question->id}" => __('Select a valid answer.')]);
                        }
                        $selectedOption = (int) $answer;
                        $isCorrect = $selectedOption === (int) $question->correct_option;
                        $marksAwarded = $isCorrect ? $question->marks : 0;
                    }
                    $autoScore += $marksAwarded;
                } elseif ($question->type === 'short_answer') {
                    if ($answer !== null && ! is_string($answer)) {
                        throw ValidationException::withMessages(["answers.{$question->id}" => __('Enter a text answer.')]);
                    }
                    $answerText = $answer === null ? null : mb_substr(trim($answer), 0, 5000);
                    $hasManualQuestions = true;
                }

                $question->update([
                    'selected_option' => $selectedOption,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                    'marks_awarded' => $marksAwarded,
                ]);
            }

            $lockedAttempt->update([
                'status' => $hasManualQuestions ? 'pending_review' : 'graded',
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'manual_score' => 0,
                'final_score' => $hasManualQuestions ? null : $autoScore,
            ]);
        });

        return redirect()->route('student.question-sets.attempts.result', [$course, $questionSet, $attempt])
            ->with('success', __('Your quiz has been submitted.'));
    }

    public function result(Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): View
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($attempt->course_id === $course->id
            && $attempt->question_set_id === $questionSet->id
            && $attempt->student_id === $student->id, 404);
        $this->assertCourseAccess($course, $questionSet, $student->id);
        abort_unless($attempt->status !== 'in_progress', 404);
        $attempt->load(['questions' => fn ($query) => $query->select(['id', 'question_set_attempt_id', 'type', 'question', 'options', 'marks', 'position', 'selected_option', 'answer_text', 'is_correct', 'marks_awarded', 'graded_at', 'manual_feedback'])]);

        return view('student.question-sets.result', compact('course', 'questionSet', 'attempt'));
    }

    public function reviewIndex(Course $course, QuestionSet $questionSet): View
    {
        $reviewer = auth()->user();
        abort_unless($reviewer && ($reviewer->isAdmin() || ($reviewer->isTeacher() && $course->teacher_id === $reviewer->id)), 403);
        abort_unless($course->questionSets()->where('question_sets.id', $questionSet->id)->exists(), 404);

        $attempts = QuestionSetAttempt::query()
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->where('status', 'pending_review')
            ->with('student')
            ->withCount('questions')
            ->latest('submitted_at')
            ->paginate(20);

        return view('teacher.question-sets.review-index', compact('course', 'questionSet', 'attempts'));
    }

    public function review(Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): View
    {
        $reviewer = auth()->user();
        abort_unless($reviewer && ($reviewer->isAdmin() || ($reviewer->isTeacher() && $course->teacher_id === $reviewer->id)), 403);
        abort_unless($attempt->course_id === $course->id && $attempt->question_set_id === $questionSet->id, 404);
        abort_unless($course->questionSets()->where('question_sets.id', $questionSet->id)->exists(), 404);
        abort_unless($attempt->status === 'pending_review', 404);
        $attempt->load(['student', 'questions']);

        return view('teacher.question-sets.review', compact('course', 'questionSet', 'attempt'));
    }

    public function grade(Request $request, Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): RedirectResponse
    {
        $reviewer = $request->user();
        abort_unless($reviewer && ($reviewer->isAdmin() || ($reviewer->isTeacher() && $course->teacher_id === $reviewer->id)), 403);
        abort_unless($attempt->course_id === $course->id && $attempt->question_set_id === $questionSet->id, 404);
        abort_unless($course->questionSets()->where('question_sets.id', $questionSet->id)->exists(), 404);

        DB::transaction(function () use ($request, $attempt, $reviewer) {
            $locked = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_review') {
                throw ValidationException::withMessages(['attempt' => __('This attempt is no longer awaiting review.')]);
            }

            $questions = $locked->questions()->where('type', 'short_answer')->get();
            $scores = $request->validate([
                'scores' => ['required', 'array'],
                'scores.*' => ['required', 'integer', 'min:0'],
                'feedback' => ['nullable', 'string', 'max:5000'],
                'question_feedback' => ['nullable', 'array'],
                'question_feedback.*' => ['nullable', 'string', 'max:2000'],
            ]);
            $expectedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
            if (array_diff($expectedIds, array_map('strval', array_keys($scores['scores'])))
                || array_diff(array_map('strval', array_keys($scores['scores'])), $expectedIds)) {
                throw ValidationException::withMessages(['scores' => __('Grade every short-answer question in this attempt.')]);
            }

            $manualScore = 0;
            foreach ($questions as $question) {
                $score = (int) $scores['scores'][$question->id];
                if ($score > $question->marks) {
                    throw ValidationException::withMessages(["scores.{$question->id}" => __('Marks cannot exceed the question maximum.')]);
                }
                $question->update([
                    'marks_awarded' => $score,
                    'graded_by' => $reviewer->id,
                    'graded_at' => now(),
                    'manual_feedback' => $scores['question_feedback'][$question->id] ?? null,
                ]);
                $manualScore += $score;
            }

            $locked->update([
                'status' => 'graded',
                'manual_score' => $manualScore,
                'final_score' => $locked->auto_score + $manualScore,
                'graded_by' => $reviewer->id,
                'graded_at' => now(),
                'teacher_feedback' => $scores['feedback'] ?? null,
            ]);
        });

        return redirect()->route('question-sets.review.index', [$course, $questionSet])
            ->with('success', __('Attempt graded successfully.'));
    }

    private function assertCourseAccess(Course $course, QuestionSet $questionSet, int $studentId): void
    {
        abort_unless($course->status === 'published', 404);
        abort_unless($course->enrollments()->where('user_id', $studentId)->whereIn('status', ['active', 'completed'])->exists(), 403);
        abort_unless($course->questionSets()->where('question_sets.id', $questionSet->id)->exists(), 404);
    }
}
