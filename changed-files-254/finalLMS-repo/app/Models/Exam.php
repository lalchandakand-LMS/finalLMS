<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = ['batch_id', 'teacher_id', 'title', 'instructions', 'starts_at', 'ends_at', 'duration_minutes', 'pass_marks', 'questions_per_attempt', 'status'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'pass_marks' => 'integer',
        'questions_per_attempt' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
