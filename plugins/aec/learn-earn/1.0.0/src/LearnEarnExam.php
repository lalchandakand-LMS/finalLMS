<?php

namespace Aloms\LearnEarn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnEarnExam extends Model
{
    protected $table = 'learn_earn_exams';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'pass_marks',
        'questions_per_attempt',
        'max_attempts',
        'randomize_questions',
        'access_type',
        'price',
        'currency',
        'status',
        'show_results',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'pass_marks' => 'integer',
        'questions_per_attempt' => 'integer',
        'max_attempts' => 'integer',
        'randomize_questions' => 'boolean',
        'price' => 'decimal:2',
        'show_results' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(LearnEarnExamQuestion::class, 'exam_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
