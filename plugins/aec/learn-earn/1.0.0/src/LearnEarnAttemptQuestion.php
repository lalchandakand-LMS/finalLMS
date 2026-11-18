<?php

namespace Aloms\LearnEarn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnEarnAttemptQuestion extends Model
{
    protected $table = 'learn_earn_attempt_questions';

    protected $fillable = ['attempt_id', 'exam_question_id', 'position'];

    protected $casts = ['position' => 'integer'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExamAttempt::class, 'attempt_id');
    }

    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExamQuestion::class, 'exam_question_id');
    }
}
