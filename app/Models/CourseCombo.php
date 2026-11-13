<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCombo extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'price', 'status'];

    protected $casts = ['price' => 'decimal:2'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_combo_course')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->has('courses', '>=', 2)
            ->whereDoesntHave('courses', fn (Builder $courses) => $courses->where('status', '!=', 'published'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
