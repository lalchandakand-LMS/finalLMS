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

class StudentQuestionSetAttemptController extends Controller
{
    public function start(Course $course, QuestionSet $questionSet): RedirectResponse
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($course->enrollments()
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists(), 403);
        
        $attempt = DB::transaction(function () use ($course, $questionSet, $student) {
            $attachment = DB::table('course_question_set')
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->lockForUpdate()
                ->first();

            abort_unless($attachment && $questionSet->status === 'published', 404);
            if ($attachment->available_from && now()->lt($attachment->available_from)) {
                abort(403, __('This quiz is not available yet.'));
            }

            $existing = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->latest('id')
                ->first();

            if ($existing) {
                if ($existing->deadline_at && now()->gte($existing->deadline_at)) {
                    $this->finalizeExpiredAttempt($existing);
                } else {
                    return $existing;
                }
            }

            $attemptCount = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->where('student_id', $student->id)
                ->count();

            if ($attachment->max_attempts !== null && $attemptCount >= $attachment->max_attempts) {
                abort(403, __('You have used all allowed attempts for this quiz.'));
            }

            $sourceQuestions = $questionSet->questions()->get();
            abort_if($sourceQuestions->isEmpty(), 422, __('This quiz does not contain any questions.'));

            $questionCount = $attachment->questions_per_attempt
                ? min((int) $attachment->questions_per_attempt, $sourceQuestions->count())
                : $sourceQuestions->count();

            $selectedQuestions = $attachment->randomize_questions
                ? $sourceQuestions->shuffle()->take($questionCount)->values()
                : $sourceQuestions->take($questionCount)->values();

            $now = now();
            $deadline = $attachment->time_limit_minutes
                ? $now->copy()->addMinutes((int) $attachment->time_limit_minutes)
                : null;
            $maxScore = (int) $selectedQuestions->sum('marks');

            $attempt = QuestionSetAttempt::create([
                'question_set_id' => $questionSet->id,
                'course_id' => $course->id,
                'student_id' => $student->id,
                'attempt_number' => $attemptCount + 1,
                'status' => 'in_progress',
                'started_at' => $now,
                'deadline_at' => $deadline,
                'max_score' => $maxScore,
            ]);

            foreach ($selectedQuestions as $index => $question) {
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

        return redirect()->route('student.question-sets.show', [$course, $questionSet, $attempt]);
    }

    public function show(Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): View|RedirectResponse
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($attempt->student_id === $student->id
            && $attempt->course_id === $course->id
            && $attempt->question_set_id === $questionSet->id, 404);
        abort_unless($course->enrollments()
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists(), 403);

        if ($attempt->status === 'in_progress' && $attempt->deadline_at && now()->gte($attempt->deadline_at)) {
            $this->finalizeExpiredAttempt($attempt);
            return redirect()->route('student.question-sets.show', [$course, $questionSet, $attempt]);
        }

        $attempt->load('questions');
        $questions = $attempt->questions->map(fn (QuestionSetAttemptQuestion $question) => [
            'id' => $question->id,
            'type' => $question->type,
            'question' => $question->question,
            'options' => $question->options,
            'marks' => $question->marks,
            'position' => $question->position,
            'selected_option' => $question->selected_option,
            'answer_text' => $question->answer_text,
            'marks_awarded' => $question->marks_awarded,
        ]);

        return view('student.question-sets.attempt', compact('course', 'questionSet', 'attempt', 'questions'));
    }

    public function submit(Request $request, Course $course, QuestionSet $questionSet, QuestionSetAttempt $attempt): RedirectResponse
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($attempt->student_id === $student->id
            && $attempt->course_id === $course->id
            && $attempt->question_set_id === $questionSet->id, 404);

        DB::transaction(function () use ($request, $attempt) {
            $lockedAttempt = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedAttempt->status === 'in_progress', 409, __('This attempt has already been submitted.'));

            if ($lockedAttempt->deadline_at && now()->gte($lockedAttempt->deadline_at)) {
                $this->finalizeExpiredAttempt($lockedAttempt);
                return;
            }

            $questions = $lockedAttempt->questions()->get();
            $rules = ['answers' => ['nullable', 'array']];
            foreach ($questions as $question) {
                if (in_array($question->type, ['mcq', 'true_false'], true)) {
                    $maxOption = max(0, count($question->options ?? []) - 1);
                    $rules['answers.'.$question->id.'.selected_option'] = ['nullable', 'integer', 'min:0', 'max:'.$maxOption];
                } elseif ($question->type === 'short_answer') {
                    $rules['answers.'.$question->id.'.answer_text'] = ['nullable', 'string', 'max:5000'];
                }
            }

            $validated = $request->validate($rules);
            $answers = $validated['answers'] ?? [];
            $allowedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
            $unexpectedIds = array_diff(array_map('strval', array_keys($answers)), $allowedIds);
            if ($unexpectedIds !== []) {
                throw ValidationException::withMessages(['answers' => __('The submitted answers contain questions outside this attempt.')]);
            }

            $autoScore = 0;
            $hasManualQuestions = false;
            foreach ($questions as $question) {
                $answer = $answers[$question->id] ?? [];
                if (in_array($question->type, ['mcq', 'true_false'], true)) {
                    $selected = isset($answer['selected_option']) ? (int) $answer['selected_option'] : null;
                    $correct = $selected !== null && $selected === (int) $question->correct_option;
                    $awarded = $correct ? (int) $question->marks : 0;
                    $question->update([
                        'selected_option' => $selected,
                        'is_correct' => $selected === null ? null : $correct,
                        'marks_awarded' => $awarded,
                    ]);
                    $autoScore += $awarded;
                } else {
                    $hasManualQuestions = true;
                    $question->update([
                        'answer_text' => trim((string) ($answer['answer_text'] ?? '')) ?: null,
                        'is_correct' => null,
                        'marks_awarded' => 0,
                    ]);
                }
            }

            $lockedAttempt->update([
                'status' => $hasManualQuestions ? 'awaiting_manual' : 'submitted',
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'manual_score' => 0,
                'final_score' => $hasManualQuestions ? null : $autoScore,
            ]);
        });

        return redirect()->route('student.question-sets.show', [$course, $questionSet, $attempt])
            ->with('success', __('Your quiz has been submitted.'));
    }

    private function finalizeExpiredAttempt(QuestionSetAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $autoScore = 0;
        $hasManualQuestions = false;
        foreach ($attempt->questions()->get() as $question) {
            if (in_array($question->type, ['mcq', 'true_false'], true)) {
                $selected = $question->selected_option;
                $correct = $selected !== null && (int) $selected === (int) $question->correct_option;
                $awarded = $correct ? (int) $question->marks : 0;
                $question->update(['is_correct' => $selected === null ? null : $correct, 'marks_awarded' => $awarded]);
                $autoScore += $awarded;
            } else {
                $hasManualQuestions = true;
            }
        }

        $attempt->update([
            'status' => $hasManualQuestions ? 'awaiting_manual' : 'submitted',
            'submitted_at' => now(),
            'auto_score' => $autoScore,
            'manual_score' => 0,
            'final_score' => $hasManualQuestions ? null : $autoScore,
        ]);
    }
}
