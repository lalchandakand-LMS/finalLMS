<?php

namespace Tests\Unit;

use Aloms\LearnEarn\LearnEarnAttemptQuestion;
use Aloms\LearnEarn\LearnEarnExamQuestion;
use Aloms\LearnEarn\LearnEarnScorer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LearnEarnScorerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnAttemptQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnScorer.php');
    }

    public function test_awards_positive_marks_deducts_negative_marks_and_ignores_unanswered_questions(): void
    {
        $questions = new Collection([
            $this->attemptQuestion(10, 2, 1, 1),
            $this->attemptQuestion(11, 3, 2, 0),
            $this->attemptQuestion(12, 1, 1, 3),
        ]);

        $result = app(LearnEarnScorer::class)->score($questions, [
            10 => 1,
            11 => 2,
        ]);

        $this->assertSame(0, $result['score']);
        $this->assertSame(6, $result['max_score']);
        $this->assertSame(2, $result['details'][10]['marks_awarded']);
        $this->assertFalse($result['details'][11]['is_correct']);
        $this->assertSame(-2, $result['details'][11]['marks_awarded']);
        $this->assertNull($result['details'][12]['selected_option']);
        $this->assertSame(0, $result['details'][12]['marks_awarded']);
    }

    public function test_final_score_is_never_negative(): void
    {
        $questions = new Collection([
            $this->attemptQuestion(20, 1, 5, 0),
            $this->attemptQuestion(21, 1, 5, 0),
        ]);

        $result = app(LearnEarnScorer::class)->score($questions, [
            20 => 1,
            21 => 1,
        ]);

        $this->assertSame(0, $result['score']);
        $this->assertSame(-5, $result['details'][20]['marks_awarded']);
    }

    private function attemptQuestion(int $id, int $marks, int $negativeMarks, int $correctOption): LearnEarnAttemptQuestion
    {
        $question = new LearnEarnExamQuestion([
            'marks' => $marks,
            'negative_marks' => $negativeMarks,
            'correct_option' => $correctOption,
        ]);
        $question->id = $id;

        $attemptQuestion = new LearnEarnAttemptQuestion();
        $attemptQuestion->setRelation('examQuestion', $question);

        return $attemptQuestion;
    }
}
