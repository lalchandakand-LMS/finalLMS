<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionSetAttempt extends Model
{
    protected $fillable = [
        'question_set_id', 'course_id', 'student_id', 'attempt_number', 'status',
        'started_at', 'deadline_at', 'submitted_at', 'auto_score', 'manual_score',
        'max_score', 'final_score', 'graded_by', 'graded_at', 'teacher_feedback',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
        'auto_score' => 'integer',
        'manual_score' => 'integer',
        'max_score' => 'integer',
        'final_score' => 'integer',
        'graded_at' => 'datetime',
    ];

    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionSetAttemptQuestion::class)->orderBy('position');
    }
}
