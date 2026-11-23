<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankQuestion extends Model
{
    protected $fillable = ['teacher_id', 'subject', 'category', 'difficulty', 'question', 'type', 'options', 'correct_option', 'correct_answer', 'explanation', 'grading_rubric', 'tags', 'marks', 'negative_marks'];

    protected $casts = ['options' => 'array', 'correct_option' => 'integer', 'grading_rubric' => 'array', 'tags' => 'array', 'marks' => 'integer', 'negative_marks' => 'integer'];

    public function examQuestions()
    {
        return $this->hasMany(ExamQuestion::class, 'question_bank_question_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
