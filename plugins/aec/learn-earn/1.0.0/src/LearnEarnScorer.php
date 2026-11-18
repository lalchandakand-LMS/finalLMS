<?php

namespace Aloms\LearnEarn;

use Illuminate\Support\Collection;

final class LearnEarnScorer
{
    /**
     * Score only the question snapshots assigned to this attempt.
     *
     * @param Collection<int, LearnEarnAttemptQuestion> $attemptQuestions
     * @param array<int|string, int|string|null> $answers
     * @return array{score:int,max_score:int,details:array<int,array{selected_option:?int,is_correct:bool,marks_awarded:int}>}
     */
    public function score(Collection $attemptQuestions, array $answers): array
    {
        $rawScore = 0;
        $maxScore = 0;
        $details = [];

        foreach ($attemptQuestions as $attemptQuestion) {
            $question = $attemptQuestion->examQuestion;
            if (! $question) {
                continue;
            }

            $questionId = (int) $question->id;
            $maxScore += (int) $question->marks;
            $selected = array_key_exists($questionId, $answers) && $answers[$questionId] !== null
                ? (int) $answers[$questionId]
                : null;

            $isCorrect = $selected !== null && $selected === (int) $question->correct_option;
            $awarded = $selected === null
                ? 0
                : ($isCorrect ? (int) $question->marks : -((int) $question->negative_marks));

            $rawScore += $awarded;
            $details[$questionId] = [
                'selected_option' => $selected,
                'is_correct' => $isCorrect,
                'marks_awarded' => $awarded,
            ];
        }

        return [
            'score' => max(0, $rawScore),
            'max_score' => $maxScore,
            'details' => $details,
        ];
    }
}
