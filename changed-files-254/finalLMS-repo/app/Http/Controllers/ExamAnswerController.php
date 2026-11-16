<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamAnswerController extends Controller
{
    public function save(ExamAttempt $attempt, Request $request): JsonResponse
    {
        abort_unless($request->user()?->isStudent(), 403);

        return DB::transaction(function () use ($attempt, $request): JsonResponse {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless((int) $lockedAttempt->student_id === (int) $request->user()->id, 403);
            abort_unless($lockedAttempt->status === 'in_progress', 409, 'This exam attempt is no longer accepting answers.');

            $exam = $lockedAttempt->exam()->firstOrFail();
            abort_unless(
                Enrollment::query()
                    ->where('batch_id', $exam->batch_id)
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'active')
                    ->exists(),
                403
            );

            $deadline = min(
                $exam->ends_at->getTimestamp(),
                $lockedAttempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
            );
            abort_if(now()->getTimestamp() > $deadline + 15, 409, 'The exam submission window has closed.');

            $validated = $request->validate([
                'answers' => ['sometimes', 'array', 'max:100'],
                'answers.*' => ['required', 'integer', 'between:0,3'],
            ]);
            $answers = $validated['answers'] ?? [];
            $allowedQuestions = $lockedAttempt->attemptQuestions()
                ->whereIn('exam_question_id', array_keys($answers))
                ->pluck('exam_question_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $submittedIds = array_map('strval', array_keys($answers));

            abort_unless(count($allowedQuestions) === count($submittedIds), 422, 'An answer references a question outside this attempt.');

            foreach ($answers as $questionId => $selectedOption) {
                $lockedAttempt->answers()->updateOrCreate(
                    ['exam_question_id' => $questionId],
                    ['selected_option' => $selectedOption, 'is_correct' => false, 'marks_awarded' => 0]
                );
            }

            return response()->json([
                'saved' => count($answers),
                'saved_at' => now()->toIso8601String(),
            ]);
        });
    }
}
