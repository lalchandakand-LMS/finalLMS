<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseVersion extends Model
{
    protected $fillable = ['course_id', 'version_number', 'snapshot', 'created_by', 'change_type'];

    protected $casts = ['snapshot' => 'array'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
