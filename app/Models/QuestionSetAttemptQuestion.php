<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionSetAttemptQuestion extends Model
{
    protected $fillable = [
        'question_set_attempt_id', 'question_set_question_id', 'type', 'question',
        'options', 'correct_option', 'correct_answer', 'explanation', 'marks',
        'position', 'selected_option', 'answer_text', 'is_correct', 'marks_awarded',
        'manual_feedback', 'graded_by', 'graded_at',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option' => 'integer',
        'marks' => 'integer',
        'position' => 'integer',
        'selected_option' => 'integer',
        'is_correct' => 'boolean',
        'marks_awarded' => 'integer',
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuestionSetAttempt::class, 'question_set_attempt_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionSetQuestion::class, 'question_set_question_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
