<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    protected $fillable = ['exam_attempt_id', 'exam_question_id', 'selected_option', 'is_correct', 'marks_awarded'];

    protected $casts = ['selected_option' => 'integer', 'is_correct' => 'boolean', 'marks_awarded' => 'integer'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }
}
