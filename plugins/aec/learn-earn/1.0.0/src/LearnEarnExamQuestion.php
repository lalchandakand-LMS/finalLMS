<?php

namespace Aloms\LearnEarn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnEarnExamQuestion extends Model
{
    protected $table = 'learn_earn_exam_questions';

    protected $fillable = [
        'exam_id',
        'source_question_id',
        'question',
        'options',
        'correct_option',
        'marks',
        'negative_marks',
        'explanation',
        'category_label',
        'position',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option' => 'integer',
        'marks' => 'integer',
        'negative_marks' => 'integer',
        'position' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExam::class, 'exam_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(LearnEarnQuestion::class, 'source_question_id');
    }
}
