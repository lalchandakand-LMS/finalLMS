<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseContentPostVersion extends Model
{
    protected $fillable = [
        'course_content_post_id', 'version_number', 'snapshot', 'created_by', 'change_type',
    ];

    protected $casts = ['snapshot' => 'array'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CourseContentPost::class, 'course_content_post_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
