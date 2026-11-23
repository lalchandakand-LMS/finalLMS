<?php

namespace App\Services;

use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

class ExamAttemptFinalizer
{
    public function autoSubmitExpired(int $attemptId): bool
    {
        return DB::transaction(function () use ($attemptId): bool {
            $attempt = ExamAttempt::query()->lockForUpdate()->find($attemptId);
            if (! $attempt || $attempt->status !== 'in_progress') return false;

            $exam = $attempt->exam()->firstOrFail();
            $deadline = min(
                $exam->ends_at->getTimestamp(),
                $attempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
            );
            if (now()->getTimestamp() <= $deadline) return false;

            $answers = $attempt->answers()->get()->keyBy('exam_question_id');
            $rules = $exam->grading_rules ?? [];
            $score = 0;
            $manualPending = false;

            foreach ($attempt->attemptQuestions()->with('question')->get() as $attemptQuestion) {
                $question = $attemptQuestion->question;
                $answer = $question ? ($answers[$question->id] ?? null) : null;
                if (! $question || ! $answer) continue;

                $type = $question->type ?: 'mcq';
                if ($type === 'written') {
                    if (trim((string) $answer->answer_text) !== '') $manualPending = true;
                    continue;
                }

                if ($type === 'short_answer') {
                    $expected = trim((string) $question->correct_answer);
                    $actual = trim((string) $answer->answer_text);
                    if ($actual === '') {
                        $answer->update(['is_correct' => false, 'marks_awarded' => 0]);
                        continue;
                    }
                    if (! ($rules['short_answer_case_sensitive'] ?? false)) {
                        $expected = mb_strtolower(preg_replace('/\s+/u', ' ', $expected));
                        $actual = mb_strtolower(preg_replace('/\s+/u', ' ', $actual));
                    }
                    $correct = $expected !== '' && $actual === $expected;
                } else {
                    $correct = $answer->selected_option !== null
                        && (int) $answer->selected_option === (int) $question->correct_option;
                }

                $penalty = (bool) ($rules['negative_marking_enabled'] ?? true)
                    ? (int) ($question->negative_marks ?? 0) * (float) ($rules['negative_marking_multiplier'] ?? 1)
                    : 0;
                $awarded = $correct ? (int) $question->marks : (int) round(-$penalty);
                $answer->update(['is_correct' => $correct, 'marks_awarded' => $awarded]);
                $score += $awarded;
            }

            $attempt->update([
                'submitted_at' => now(),
                'score' => ($rules['score_floor_zero'] ?? true) ? max(0, $score) : $score,
                'status' => $manualPending ? 'awaiting_manual' : 'submitted',
                'auto_submitted' => true,
            ]);

            return true;
        });
    }
}
