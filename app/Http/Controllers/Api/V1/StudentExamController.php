<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isStudent(), 403);

        $exams = Exam::query()
            ->with('batch.course:id,title')
            ->where('status', 'published')
            ->whereHas('batch.enrollments', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', 'active'))
            ->with(['attempts' => fn ($query) => $query
                ->where('student_id', $request->user()->id)
                ->select('id', 'exam_id', 'status', 'started_at', 'submitted_at', 'score')])
            ->orderBy('starts_at')
            ->paginate(20);

        return response()->json([
            'data' => $exams->getCollection()->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'instructions' => $exam->instructions,
                'course' => $exam->batch?->course?->title,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'pass_marks' => $exam->pass_marks,
                'attempt' => $this->attemptSummary($exam->attempts->first()),
            ]),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'last_page' => $exams->lastPage(),
                'per_page' => $exams->perPage(),
                'total' => $exams->total(),
            ],
        ]);
    }

    public function show(Request $request, Exam $exam): JsonResponse
    {
        $this->ensureEnrolled($request, $exam);
        abort_unless($exam->status === 'published', 404);

        $attempt = $exam->attempts()
            ->where('student_id', $request->user()->id)
            ->with(['attemptQuestions.question', 'answers'])
            ->first();

        return response()->json([
            'data' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'instructions' => $exam->instructions,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'attempt' => $this->attemptSummary($attempt),
                'questions' => $attempt && $attempt->status === 'in_progress'
                    ? $this->questionPayload($attempt)
                    : [],
                'result' => $attempt && $attempt->status === 'submitted'
                    ? ['score' => $attempt->score, 'submitted_at' => $attempt->submitted_at?->toIso8601String()]
                    : null,
            ],
        ]);
    }

    public function start(Request $request, Exam $exam): JsonResponse
    {
        $this->ensureEnrolled($request, $exam);

        $attempt = DB::transaction(function () use ($request, $exam): ExamAttempt {
            $lockedExam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $this->ensureEnrolled($request, $lockedExam);
            abort_unless(
                $lockedExam->status === 'published'
                    && now()->between($lockedExam->starts_at, $lockedExam->ends_at),
                403,
                'This exam is not currently available.'
            );
            abort_unless($lockedExam->questions()->exists(), 422, 'This exam has no questions.');

            $attempt = ExamAttempt::query()
                ->where('exam_id', $lockedExam->id)
                ->where('student_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $attempt = ExamAttempt::create([
                    'exam_id' => $lockedExam->id,
                    'student_id' => $request->user()->id,
                    'started_at' => now(),
                    'status' => 'in_progress',
                ]);
            }

            if ($attempt->status === 'in_progress' && ! $attempt->attemptQuestions()->exists()) {
                $questionQuery = $lockedExam->questions()->reorder()->inRandomOrder();
                if ($lockedExam->questions_per_attempt) {
                    $questionQuery->limit($lockedExam->questions_per_attempt);
                }
                $ids = $questionQuery->pluck('id')->values();
                abort_if($ids->isEmpty(), 422, 'No questions are available.');
                $attempt->attemptQuestions()->createMany($ids->map(
                    fn ($id, $position) => ['exam_question_id' => $id, 'position' => $position + 1]
                )->all());
            }

            return $attempt;
        });

        $attempt->load(['attemptQuestions.question', 'answers']);

        return response()->json([
            'data' => [
                'attempt' => $this->attemptSummary($attempt),
                'deadline' => $this->deadline($attempt)->toIso8601String(),
                'questions' => $attempt->status === 'in_progress' ? $this->questionPayload($attempt) : [],
            ],
        ]);
    }

    public function saveAnswers(Request $request, ExamAttempt $attempt): JsonResponse
    {
        abort_unless($request->user()?->isStudent(), 403);

        $saved = DB::transaction(function () use ($request, $attempt): int {
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless((int) $locked->student_id === (int) $request->user()->id, 403);
            abort_unless($locked->status === 'in_progress', 409, 'This attempt is no longer accepting answers.');
            $exam = $locked->exam()->firstOrFail();
            $this->ensureEnrolled($request, $exam);
            abort_if(now()->gt($this->deadline($locked)->addSeconds(15)), 409, 'The exam time has expired.');

            $validated = $request->validate([
                'answers' => ['required', 'array', 'min:1', 'max:100'],
                'answers.*' => ['required', 'integer', 'between:0,3'],
            ]);
            $answers = $validated['answers'];
            $allowed = $locked->attemptQuestions()
                ->whereIn('exam_question_id', array_keys($answers))
                ->pluck('exam_question_id')->map(fn ($id) => (string) $id)->all();
            abort_unless(count($allowed) === count($answers), 422, 'An answer references a question outside this attempt.');

            foreach ($answers as $questionId => $option) {
                $locked->answers()->updateOrCreate(
                    ['exam_question_id' => $questionId],
                    ['selected_option' => $option, 'is_correct' => false, 'marks_awarded' => 0]
                );
            }

            return count($answers);
        });

        return response()->json(['saved' => $saved, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse
    {
        abort_unless($request->user()?->isStudent(), 403);

        $score = DB::transaction(function () use ($request, $attempt): int {
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless((int) $locked->student_id === (int) $request->user()->id, 403);
            abort_unless($locked->status === 'in_progress', 409, 'This attempt has already been submitted.');
            $exam = $locked->exam()->firstOrFail();
            $this->ensureEnrolled($request, $exam);
            abort_if(now()->gt($this->deadline($locked)->addSeconds(15)), 409, 'The exam submission window has closed.');

            $validated = $request->validate([
                'answers' => ['sometimes', 'array', 'max:100'],
                'answers.*' => ['required', 'integer', 'between:0,3'],
            ]);
            $submitted = $validated['answers'] ?? [];
            $questions = $locked->attemptQuestions()->with('question')->orderBy('position')->get();
            abort_if($questions->isEmpty(), 422, 'No questions are assigned to this attempt.');
            $allowedIds = $questions->pluck('exam_question_id')->map(fn ($id) => (string) $id)->all();
            abort_unless(count(array_diff(array_map('strval', array_keys($submitted)), $allowedIds)) === 0, 422, 'An answer references a question outside this attempt.');

            $savedAnswers = $locked->answers()->get()->keyBy('exam_question_id');
            $total = 0;
            foreach ($questions as $item) {
                $question = $item->question;
                if (! $question) continue;
                $id = (string) $question->id;
                $selected = array_key_exists($id, $submitted)
                    ? (int) $submitted[$id]
                    : ($savedAnswers[$question->id]->selected_option ?? null);
                if ($selected === null) continue;
                $correct = $selected === (int) $question->correct_option;
                $marks = $correct ? (int) $question->marks : 0;
                $total += $marks;
                $locked->answers()->updateOrCreate(
                    ['exam_question_id' => $question->id],
                    ['selected_option' => $selected, 'is_correct' => $correct, 'marks_awarded' => $marks]
                );
            }
            $locked->update(['submitted_at' => now(), 'score' => $total, 'status' => 'submitted']);
            return $total;
        });

        return response()->json([
            'data' => ['score' => $score, 'submitted_at' => now()->toIso8601String(), 'status' => 'submitted'],
        ]);
    }

    private function ensureEnrolled(Request $request, Exam $exam): void
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless(Enrollment::query()
            ->where('batch_id', $exam->batch_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists(), 403);
    }

    private function deadline(ExamAttempt $attempt): \Illuminate\Support\Carbon
    {
        $exam = $attempt->exam;
        $durationDeadline = $attempt->started_at->copy()->addMinutes($exam->duration_minutes);
        return $durationDeadline->lessThan($exam->ends_at) ? $durationDeadline : $exam->ends_at->copy();
    }

    private function attemptSummary(?ExamAttempt $attempt): ?array
    {
        if (! $attempt) return null;
        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'score' => $attempt->status === 'submitted' ? $attempt->score : null,
        ];
    }

    private function questionPayload(ExamAttempt $attempt): array
    {
        $answers = $attempt->answers->keyBy('exam_question_id');
        return $attempt->attemptQuestions->map(function ($item) use ($answers): array {
            $question = $item->question;
            if (! $question) return [];
            return [
                'id' => $question->id,
                'question' => $question->question,
                'options' => $question->options,
                'marks' => $question->marks,
                'position' => $item->position,
                'selected_option' => $answers[$question->id]->selected_option ?? null,
            ];
        })->filter()->values()->all();
    }
}
