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
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
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
