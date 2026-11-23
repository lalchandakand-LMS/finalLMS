<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'started_at', 'submitted_at', 'score', 'status', 'auto_submitted', 'last_saved_at', 'graded_at', 'graded_by'];

    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'score' => 'integer', 'auto_submitted' => 'boolean', 'last_saved_at' => 'datetime', 'graded_at' => 'datetime'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('position');
    }
}
