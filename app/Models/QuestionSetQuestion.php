<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionSetQuestion extends Model
{
    protected $fillable = [
        'question_set_id', 'source_question_id', 'type', 'question', 'options',
        'correct_option', 'correct_answer', 'explanation', 'difficulty', 'tags',
        'marks', 'position',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option' => 'integer',
        'tags' => 'array',
        'marks' => 'integer',
        'position' => 'integer',
    ];

    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class, 'source_question_id');
    }
}
