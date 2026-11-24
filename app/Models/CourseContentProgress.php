<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseContentProgress extends Model
{
    protected $fillable = [
        'user_id',
        'course_curriculum_item_id',
        'last_viewed_at',
        'completed_at',
        'progress_percent',
        'watched_seconds',
        'duration_seconds',
        'last_position_seconds',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percent' => 'decimal:2',
        'watched_seconds' => 'integer',
        'duration_seconds' => 'integer',
        'last_position_seconds' => 'integer',
    ];

    public function curriculumItem(): BelongsTo
    {
        return $this->belongsTo(CourseCurriculumItem::class, 'course_curriculum_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
