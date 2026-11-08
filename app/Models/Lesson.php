<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'content',
        'duration_minutes',
        'order',
        'is_preview',
        'video_url',\n        'attachment_path',\n        'attachment_name',\n        'attachment_mime',\n        'video_path',\n        'video_name',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
