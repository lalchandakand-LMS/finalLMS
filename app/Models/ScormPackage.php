<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScormPackage extends Model
{
    protected $fillable = ['course_id', 'uploaded_by', 'title', 'manifest_title', 'entry_point', 'archive_path', 'archive_sha256', 'files'];

    protected function casts(): array
    {
        return ['files' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
