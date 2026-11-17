<?php

namespace Aloms\LearnEarn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnEarnExamAnswer extends Model
{
    protected $table = 'learn_earn_exam_answers';

    protected $fillable = [
        'attempt_id',
        'exam_question_id',
        'selected_option',
        'is_correct',
        'marks_awarded',
    ];

    protected $casts = [
        'selected_option' => 'integer',
        'is_correct' => 'boolean',
        'marks_awarded' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExamAttempt::class, 'attempt_id');
    }

    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExamQuestion::class, 'exam_question_id');
    }
}
