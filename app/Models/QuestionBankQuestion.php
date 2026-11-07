<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankQuestion extends Model
{
    protected $fillable = ['teacher_id', 'subject', 'category', 'difficulty', 'question', 'options', 'correct_option', 'marks'];

    protected $casts = ['options' => 'array', 'correct_option' => 'integer', 'marks' => 'integer'];

    public function examQuestions()
    {
        return $this->hasMany(ExamQuestion::class, 'question_bank_question_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
