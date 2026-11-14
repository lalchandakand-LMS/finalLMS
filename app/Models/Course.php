<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'title',
        'slug',
        'description',
        'price',
        'status',
        'image_path',
        'short_description', 'tags', 'seo_title', 'seo_description',
        'learning_outcomes', 'requirements', 'level', 'language', 'duration_minutes',
    ];

    protected $casts = ['tags' => 'array'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CourseCategory::class, 'course_category_course')->withTimestamps();
    }

    public function flashSales(): BelongsToMany
    {
        return $this->belongsToMany(FlashSale::class, 'flash_sale_course')->withTimestamps();
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(CourseCombo::class, 'course_combo_course')->withPivot('position')->withTimestamps();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CourseContentPost::class, 'course_curriculum_items')
            ->withPivot(['section_title', 'position', 'is_preview', 'duration_minutes'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function curriculumItems(): HasMany
    {
        return $this->hasMany(CourseCurriculumItem::class)->orderBy('position');
    }

    public function questionSets(): BelongsToMany
    {
        return $this->belongsToMany(QuestionSet::class, 'course_question_set')
            ->withPivot(['section_title', 'position', 'max_attempts', 'time_limit_minutes', 'questions_per_attempt', 'randomize_questions', 'available_from'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->where('status', 'approved');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
