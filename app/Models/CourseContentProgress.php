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
        'last_position_seconds',
        'duration_seconds',
        'progress_percent',
        'completed_at',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
        'last_position_seconds' => 'integer',
        'duration_seconds' => 'integer',
        'progress_percent' => 'decimal:2',
        'completed_at' => 'datetime',
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
