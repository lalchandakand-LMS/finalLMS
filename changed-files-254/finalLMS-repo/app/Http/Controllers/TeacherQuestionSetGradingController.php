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

class TeacherQuestionSetGradingController extends Controller
{
    public function index(Course $course, QuestionSet $questionSet): View
    {
        $this->authorizeCourse($course);
        abort_unless($course->questionSets()->whereKey($questionSet->id)->exists(), 404);

        $attempts = QuestionSetAttempt::query()
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->whereIn('status', ['awaiting_manual', 'graded'])
            ->with('student')
            ->withCount(['questions as written_questions_count' => fn ($questions) => $questions->where('type', 'short_answer')])
            ->orderByRaw("CASE WHEN status = 'awaiting_manual' THEN 0 ELSE 1 END")
            ->latest('submitted_at')
            ->paginate(20);

        return view('teacher.question-sets.index', compact('course', 'questionSet', 'attempts'));
    }

    public function show(QuestionSetAttempt $attempt): View
    {
        $attempt->load(['course', 'questionSet', 'student', 'questions']);
        $this->authorizeCourse($attempt->course);
        abort_unless(in_array($attempt->status, ['awaiting_manual', 'graded'], true), 404);

        $questions = $attempt->questions;

        return view('teacher.question-sets.grade', compact('attempt', 'questions'));
    }

    public function grade(Request $request, QuestionSetAttempt $attempt): RedirectResponse
    {
        $attempt->load('course');
        $this->authorizeCourse($attempt->course);

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'array'],
            'answers.*.marks_awarded' => ['required', 'integer', 'min:0', 'max:10000'],
            'answers.*.feedback' => ['nullable', 'string', 'max:1000'],
            'teacher_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($attempt, $validated): void {
            $lockedAttempt = QuestionSetAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedAttempt->status === 'awaiting_manual', 409, __('This quiz attempt has already been graded or is not awaiting review.'));

            $writtenQuestions = $lockedAttempt->questions()
                ->where('type', 'short_answer')
                ->lockForUpdate()
                ->get();

            abort_if($writtenQuestions->isEmpty(), 409, __('This attempt has no written answers to grade.'));

            $answers = $validated['answers'];
            $allowedIds = $writtenQuestions->pluck('id')->map(fn ($id) => (string) $id)->all();
            $submittedIds = array_map('strval', array_keys($answers));

            if (array_diff($submittedIds, $allowedIds) !== []) {
                throw ValidationException::withMessages([
                    'answers' => __('The grading form contains a question outside this attempt.'),
                ]);
            }

            $missingIds = array_diff($allowedIds, $submittedIds);
            if ($missingIds !== []) {
                throw ValidationException::withMessages([
                    'answers' => __('Grade every written answer before publishing the final result.'),
                ]);
            }

            $manualScore = 0;
            $graderId = auth()->id();
            $gradedAt = now();

            foreach ($writtenQuestions as $question) {
                $answer = $answers[$question->id];
                $marks = (int) $answer['marks_awarded'];
                if ($marks > $question->marks) {
                    throw ValidationException::withMessages([
                        'answers.'.$question->id.'.marks_awarded' => __('Marks awarded cannot exceed the question marks.'),
                    ]);
                }

                $question->update([
                    'marks_awarded' => $marks,
                    'graded_by' => $graderId,
                    'graded_at' => $gradedAt,
                    'manual_feedback' => $answer['feedback'] ?? null,
                ]);
                $manualScore += $marks;
            }

            $autoScore = (int) $lockedAttempt->auto_score;
            $lockedAttempt->update([
                'status' => 'graded',
                'manual_score' => $manualScore,
                'final_score' => $autoScore + $manualScore,
                'graded_by' => $graderId,
                'graded_at' => $gradedAt,
                'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            ]);
        });

        return redirect()->route('teacher.question-set-attempts.show', $attempt)
            ->with('success', __('Written answers graded and final result published.'));
    }

    private function authorizeCourse(Course $course): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || ($user->isTeacher() && (int) $course->teacher_id === (int) $user->id)), 403);
    }
}
