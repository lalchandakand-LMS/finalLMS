<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionSet extends Model
{
    protected $fillable = ['title', 'description', 'status', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionSetQuestion::class)->orderBy('position');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_question_set')
            ->withPivot(['section_title', 'position', 'max_attempts', 'time_limit_minutes', 'questions_per_attempt', 'randomize_questions', 'available_from'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
