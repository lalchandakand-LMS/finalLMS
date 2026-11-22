<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCurriculumItem extends Model
{
    protected $fillable = [
        'course_id', 'course_content_post_id', 'section_title',
        'position', 'is_preview', 'duration_minutes', 'unlock_after_previous', 'prerequisite_post_id',
    ];

    protected $casts = ['is_preview' => 'boolean', 'unlock_after_previous' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function prerequisitePost(): BelongsTo
    {
        return $this->belongsTo(CourseContentPost::class, 'prerequisite_post_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CourseContentPost::class, 'course_content_post_id');
    }
}
