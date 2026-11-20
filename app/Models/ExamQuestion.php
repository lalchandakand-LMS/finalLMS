<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $fillable = ['exam_id', 'question_bank_question_id', 'question', 'options', 'correct_option', 'marks', 'negative_marks', 'position'];

    protected $casts = ['options' => 'array', 'correct_option' => 'integer', 'marks' => 'integer', 'negative_marks' => 'integer', 'position' => 'integer'];

    public function questionBankQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
