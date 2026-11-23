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
            abort_unless(Enrollment::query()
                ->where('batch_id', $exam->batch_id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->exists(), 403);

            $deadline = min(
                $exam->ends_at->getTimestamp(),
                $lockedAttempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
            );
            abort_if(now()->getTimestamp() > $deadline + 15, 409, 'The exam submission window has closed.');

            $validated = $request->validate(['answers' => ['sometimes', 'array', 'max:100']]);
            $answers = $validated['answers'] ?? [];
            $allowed = $lockedAttempt->attemptQuestions()->with('question')->get()->keyBy(fn ($row) => (string) $row->exam_question_id);
            foreach ($answers as $questionId => $value) {
                abort_unless(isset($allowed[(string) $questionId]), 422, 'An answer references a question outside this attempt.');
                abort_unless(is_string($value) || is_int($value), 422, 'Invalid answer format.');
                $question = $allowed[(string) $questionId]->question;
                abort_unless($question, 422);
                $type = $question->type ?: 'mcq';
                if (in_array($type, ['mcq', 'true_false'], true)) {
                    abort_unless(filter_var($value, FILTER_VALIDATE_INT) !== false
                        && (int) $value >= 0
                        && (int) $value < count($question->options ?? []), 422, 'Invalid option selected.');
                    $lockedAttempt->answers()->updateOrCreate(
                        ['exam_question_id' => $question->id],
                        ['selected_option' => (int) $value, 'answer_text' => null, 'is_correct' => false, 'marks_awarded' => 0]
                    );
                } else {
                    $text = trim((string) $value);
                    abort_if(mb_strlen($text) > 10000, 422, 'Answer is too long.');
                    $lockedAttempt->answers()->updateOrCreate(
                        ['exam_question_id' => $question->id],
                        ['selected_option' => null, 'answer_text' => $text, 'is_correct' => false, 'marks_awarded' => 0]
                    );
                }
            }

            $lockedAttempt->update(['last_saved_at' => now()]);

            return response()->json([
                'saved' => count($answers),
                'saved_at' => now()->toIso8601String(),
            ]);
        });
    }
}
