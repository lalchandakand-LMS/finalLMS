<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCurriculumItem extends Model
{
    protected $fillable = [
        'course_id', 'course_content_post_id', 'section_title',
        'position', 'is_preview', 'duration_minutes',
    ];

    protected $casts = ['is_preview' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CourseContentPost::class, 'course_content_post_id');
    }
}
