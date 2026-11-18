<?php

namespace Aloms\LearnEarn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnEarnExamAttempt extends Model
{
    protected $table = 'learn_earn_exam_attempts';

    protected $fillable = [
        'exam_id',
        'student_id',
        'attempt_number',
        'status',
        'started_at',
        'ends_at',
        'submitted_at',
        'score',
        'max_score',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'integer',
        'max_score' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(LearnEarnAttemptQuestion::class, 'attempt_id')->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LearnEarnExamAnswer::class, 'attempt_id');
    }
}
