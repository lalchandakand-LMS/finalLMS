<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $fillable = ['exam_id', 'question_bank_question_id', 'question_set_question_id', 'question', 'type', 'options', 'correct_option', 'correct_answer', 'explanation', 'grading_rubric', 'marks', 'negative_marks', 'position'];

    protected $casts = ['options' => 'array', 'correct_option' => 'integer', 'grading_rubric' => 'array', 'marks' => 'integer', 'negative_marks' => 'integer', 'position' => 'integer'];

    public function questionSetQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionSetQuestion::class);
    }

    public function questionBankQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
