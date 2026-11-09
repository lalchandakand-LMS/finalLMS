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
        'course_id',\n        'section_id',
        'title',
        'content',
        'duration_minutes',
        'order',
        'is_preview',
        'video_url',
    ];

    public function section(): BelongsTo\n    {\n        return $this->belongsTo(CourseSection::class, 'section_id');\n    }\n\n    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
