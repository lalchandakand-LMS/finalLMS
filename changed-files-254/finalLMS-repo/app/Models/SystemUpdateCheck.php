<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateCheck extends Model
{
    protected $fillable = [
        'checked_by',
        'status',
        'current_version',
        'target_version',
        'manifest_sha256',
        'git_commit',
        'message',
        'details',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
