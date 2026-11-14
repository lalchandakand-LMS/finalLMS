<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\QuestionSetAttemptQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentCourseQuizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);

        $courses = Course::query()
            ->where('status', 'published')
            ->whereHas('enrollments', fn ($query) => $query
                ->where('user_id', $student->id)
                ->whereIn('status', ['active', 'completed']))
            ->whereHas('questionSets', fn ($query) => $query->where('question_sets.status', 'published'))
            ->with(['questionSets' => fn ($query) => $query
                ->where('question_sets.status', 'published')
                ->withPivot([
                    'available_from',
                    'max_attempts',
                    'time_limit_minutes',
                    'questions_per_attempt',
                    'randomize_questions',
                    'position',
                ])])
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $courses->map(fn (Course $course) => [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'course_slug' => $course->slug,
                'quizzes' => $course->questionSets->map(function (QuestionSet $set) use ($student, $course): array {
                    $used = QuestionSetAttempt::query()
                        ->where('course_id', $course->id)
                        ->where('question_set_id', $set->id)
                        ->where('student_id', $student->id)
                        ->count();
                    $latest = QuestionSetAttempt::query()
                        ->where('course_id', $course->id)
                        ->where('question_set_id', $set->id)
                        ->where('student_id', $student->id)
                        ->latest('attempt_number')
                        ->first();
                    $maximum = $set->pivot->max_attempts === null ? null : (int) $set->pivot->max_attempts;

                    return [
                        'id' => $set->id,
                        'title' => $set->title,
                        'description' => $set->description,
                        'available_from' => $set->pivot->available_from,
                        'time_limit_minutes' => $set->pivot->time_limit_minutes,
                        'questions_per_attempt' => $set->pivot->questions_per_attempt,
                        'randomize_questions' => (bool) $set->pivot->randomize_questions,
                        'attempts_used' => $used,
                        'max_attempts' => $maximum,
                        'attempts_remaining' => $maximum === null ? null : max(0, $maximum - $used),
                        'latest_attempt' => $this->attemptSummary($latest),
                    ];
                })->values(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Course $course, QuestionSet $questionSet): JsonResponse
    {
        $student = $request->user();
        $pivot = $this->authorizeCourseQuiz($student, $course, $questionSet);

        $attempt = QuestionSetAttempt::query()
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->where('student_id', $student->id)
            ->latest('attempt_number')
            ->first();

        if ($attempt && $attempt->status === 'in_progress'
            && $attempt->deadline_at && now()->gte($attempt->deadline_at)) {
            DB::transaction(function () use ($attempt): void {
                $locked = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $this->finalizeExpiredAttempt($locked);
            });
            $attempt->refresh();
        }

        $attempt?->load('questions');
        $used = QuestionSetAttempt::query()
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->where('student_id', $student->id)
            ->count();
        $maximum = $pivot->max_attempts === null ? null : (int) $pivot->max_attempts;

        return response()->json([
            'data' => [
                'course' => ['id' => $course->id, 'title' => $course->title, 'slug' => $course->slug],
                'quiz' => [
                    'id' => $questionSet->id,
                    'title' => $questionSet->title,
                    'description' => $questionSet->description,
                    'question_count' => $questionSet->questions()->count(),
                    'time_limit_minutes' => $pivot->time_limit_minutes,
                ],
                'attempt' => $this->attemptPayload($attempt),
                'available_from' => $pivot->available_from,
                'max_attempts' => $maximum,
                'attempts_used' => $used,
                'attempts_remaining' => $maximum === null ? null : max(0, $maximum - $used),
            ],
        ]);
    }

    public function start(Request $request, Course $course, QuestionSet $questionSet): JsonResponse
    {
        $student = $request->user();
        $this->authorizeCourseQuiz($student, $course, $questionSet);

        $attempt = DB::transaction(function () use ($student, $course, $questionSet): QuestionSetAttempt {
            $pivot = DB::table('course_question_set')
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->lockForUpdate()
                ->first();
            abort_unless($pivot && $questionSet->status === 'published', 404);

            if ($pivot->available_from && now()->lt($pivot->available_from)) {
                abort(403, __('This quiz is not available yet.'));
            }

            $existing = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->deadline_at && now()->gte($existing->deadline_at)) {
                    $this->finalizeExpiredAttempt($existing);
                } else {
                    return $existing;
                }
            }

            $used = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('question_set_id', $questionSet->id)
                ->where('student_id', $student->id)
                ->count();
            if ($pivot->max_attempts !== null && $used >= (int) $pivot->max_attempts) {
                abort(403, __('You have used all allowed attempts for this quiz.'));
            }

            $source = $questionSet->questions()->get();
            abort_if($source->isEmpty(), 422, __('This quiz does not contain any questions.'));
            $count = $pivot->questions_per_attempt
                ? min((int) $pivot->questions_per_attempt, $source->count())
                : $source->count();
            $selected = (bool) $pivot->randomize_questions
                ? $source->shuffle()->take($count)->values()
                : $source->take($count)->values();

            $now = now();
            $deadline = $pivot->time_limit_minutes
                ? $now->copy()->addMinutes((int) $pivot->time_limit_minutes)
                : null;
            $attempt = QuestionSetAttempt::create([
                'question_set_id' => $questionSet->id,
                'course_id' => $course->id,
                'student_id' => $student->id,
                'attempt_number' => $used + 1,
                'status' => 'in_progress',
                'started_at' => $now,
                'deadline_at' => $deadline,
                'max_score' => (int) $selected->sum('marks'),
            ]);

            $attempt->questions()->createMany($selected->values()->map(
                fn ($question, $index) => [
                    'question_set_question_id' => $question->id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'marks' => $question->marks,
                    'position' => ($index + 1) * 10,
                ]
            )->all());

            return $attempt;
        });

        $attempt->load('questions');

        return response()->json([
            'data' => [
                'attempt' => $this->attemptSummary($attempt),
                'questions' => $this->questionPayload($attempt),
            ],
        ], 201);
    }

    public function attempt(Request $request, QuestionSetAttempt $attempt): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless((int) $attempt->student_id === (int) $student->id, 404);

        if ($attempt->status === 'in_progress' && $attempt->deadline_at
            && now()->gte($attempt->deadline_at)) {
            DB::transaction(function () use ($attempt): void {
                $locked = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $this->finalizeExpiredAttempt($locked);
            });
            $attempt->refresh();
        }

        $attempt->load('questions');

        return response()->json(['data' => $this->attemptPayload($attempt)]);
    }

    public function saveAnswers(Request $request, QuestionSetAttempt $attempt): JsonResponse
    {
        $student = $request->user();
        $this->authorizeAttempt($student, $attempt, true);

        return DB::transaction(function () use ($request, $attempt): JsonResponse {
            $locked = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'in_progress', 409, __('This attempt is no longer accepting answers.'));

            if ($locked->deadline_at && now()->gte($locked->deadline_at)) {
                $this->finalizeExpiredAttempt($locked);

                return response()->json(['saved' => false, 'expired' => true], 409);
            }

            $questions = $locked->questions()->lockForUpdate()->get();
            $answers = $this->validatedAnswers($request, $questions);

            foreach ($questions as $question) {
                if (! array_key_exists((string) $question->id, $answers)
                    && ! array_key_exists($question->id, $answers)) {
                    continue;
                }
                $answer = $answers[$question->id] ?? $answers[(string) $question->id];
                if (in_array($question->type, ['mcq', 'true_false'], true)) {
                    $question->update([
                        'selected_option' => isset($answer['selected_option'])
                            ? (int) $answer['selected_option']
                            : null,
                    ]);
                } else {
                    $question->update([
                        'answer_text' => trim((string) ($answer['answer_text'] ?? '')) ?: null,
                    ]);
                }
            }

            return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
        });
    }

    public function submit(Request $request, QuestionSetAttempt $attempt): JsonResponse
    {
        $student = $request->user();
        $this->authorizeAttempt($student, $attempt, true);

        $result = DB::transaction(function () use ($request, $attempt): array {
            $locked = QuestionSetAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'in_progress', 409, __('This attempt has already been submitted.'));

            if ($locked->deadline_at && now()->gte($locked->deadline_at)) {
                $this->finalizeExpiredAttempt($locked);

                return ['expired' => true, 'attempt' => $locked->fresh()];
            }

            $questions = $locked->questions()->lockForUpdate()->get();
            $answers = $this->validatedAnswers($request, $questions);
            $autoScore = 0;
            $hasManual = false;

            foreach ($questions as $question) {
                $answer = $answers[$question->id] ?? $answers[(string) $question->id] ?? [];
                if (in_array($question->type, ['mcq', 'true_false'], true)) {
                    $selected = array_key_exists('selected_option', $answer)
                        ? ($answer['selected_option'] === null ? null : (int) $answer['selected_option'])
                        : $question->selected_option;
                    $correct = $selected !== null && $selected === (int) $question->correct_option;
                    $awarded = $correct ? (int) $question->marks : 0;
                    $question->update([
                        'selected_option' => $selected,
                        'is_correct' => $selected === null ? null : $correct,
                        'marks_awarded' => $awarded,
                    ]);
                    $autoScore += $awarded;
                } else {
                    $hasManual = true;
                    $text = array_key_exists('answer_text', $answer)
                        ? trim((string) ($answer['answer_text'] ?? ''))
                        : (string) ($question->answer_text ?? '');
                    $question->update([
                        'answer_text' => $text !== '' ? $text : null,
                        'is_correct' => null,
                        'marks_awarded' => 0,
                    ]);
                }
            }

            $locked->update([
                'status' => $hasManual ? 'awaiting_manual' : 'submitted',
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'manual_score' => 0,
                'final_score' => $hasManual ? null : $autoScore,
            ]);

            return ['expired' => false, 'attempt' => $locked->fresh()];
        });

        $attempt = $result['attempt']->load('questions');

        return response()->json([
            'data' => $this->attemptPayload($attempt),
            'expired' => $result['expired'],
        ]);
    }

    private function authorizeCourseQuiz($student, Course $course, QuestionSet $questionSet): object
    {
        abort_unless($student?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($course->enrollments()
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists(), 403);
        abort_unless($questionSet->status === 'published', 404);

        $pivot = DB::table('course_question_set')
            ->where('course_id', $course->id)
            ->where('question_set_id', $questionSet->id)
            ->first();
        abort_unless($pivot, 404);

        return $pivot;
    }

    private function authorizeAttempt($student, QuestionSetAttempt $attempt, bool $requireEnrollment): void
    {
        abort_unless($student?->isStudent(), 403);
        abort_unless((int) $attempt->student_id === (int) $student->id, 404);

        if ($requireEnrollment) {
            abort_unless($attempt->course->enrollments()
                ->where('user_id', $student->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists(), 403);
        }
    }

    private function validatedAnswers(Request $request, Collection $questions): array
    {
        $rules = [
            'answers' => ['nullable', 'array'],
            'answers.*' => ['array'],
        ];
        foreach ($questions as $question) {
            if (in_array($question->type, ['mcq', 'true_false'], true)) {
                $maximum = max(0, count($question->options ?? []) - 1);
                $rules['answers.'.$question->id.'.selected_option'] = [
                    'nullable', 'integer', 'min:0', 'max:'.$maximum,
                ];
            } else {
                $rules['answers.'.$question->id.'.answer_text'] = [
                    'nullable', 'string', 'max:5000',
                ];
            }
        }

        $validated = $request->validate($rules);
        $answers = $validated['answers'] ?? [];
        $allowed = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        if (array_diff(array_map('strval', array_keys($answers)), $allowed) !== []) {
            throw ValidationException::withMessages([
                'answers' => __('The submitted answers contain questions outside this attempt.'),
            ]);
        }

        return $answers;
    }

    private function finalizeExpiredAttempt(QuestionSetAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $autoScore = 0;
        $hasManual = false;
        foreach ($attempt->questions()->get() as $question) {
            if (in_array($question->type, ['mcq', 'true_false'], true)) {
                $selected = $question->selected_option;
                $correct = $selected !== null && (int) $selected === (int) $question->correct_option;
                $awarded = $correct ? (int) $question->marks : 0;
                $question->update([
                    'is_correct' => $selected === null ? null : $correct,
                    'marks_awarded' => $awarded,
                ]);
                $autoScore += $awarded;
            } else {
                $hasManual = true;
            }
        }

        $attempt->update([
            'status' => $hasManual ? 'awaiting_manual' : 'submitted',
            'submitted_at' => now(),
            'auto_score' => $autoScore,
            'manual_score' => 0,
            'final_score' => $hasManual ? null : $autoScore,
        ]);
    }

    private function attemptPayload(?QuestionSetAttempt $attempt): ?array
    {
        if (! $attempt) {
            return null;
        }

        return [
            'attempt' => $this->attemptSummary($attempt),
            'questions' => $this->questionPayload($attempt),
        ];
    }

    private function attemptSummary(?QuestionSetAttempt $attempt): ?array
    {
        if (! $attempt) {
            return null;
        }

        return [
            'id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'deadline_at' => $attempt->deadline_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'auto_score' => $attempt->status === 'in_progress' ? null : $attempt->auto_score,
            'manual_score' => $attempt->status === 'in_progress' ? null : $attempt->manual_score,
            'max_score' => $attempt->max_score,
            'final_score' => $attempt->status === 'graded' || $attempt->status === 'submitted'
                ? $attempt->final_score
                : null,
            'teacher_feedback' => $attempt->teacher_feedback,
            'graded_at' => $attempt->graded_at?->toIso8601String(),
        ];
    }

    private function questionPayload(QuestionSetAttempt $attempt): array
    {
        return $attempt->questions->map(fn (QuestionSetAttemptQuestion $question): array => [
            'id' => $question->id,
            'type' => $question->type,
            'question' => $question->question,
            'options' => $question->options,
            'marks' => $question->marks,
            'position' => $question->position,
            'selected_option' => $question->selected_option,
            'answer_text' => $question->answer_text,
            'marks_awarded' => $attempt->status === 'in_progress' ? null : $question->marks_awarded,
            'manual_feedback' => $attempt->status === 'graded' ? $question->manual_feedback : null,
        ])->values()->all();
    }
}
