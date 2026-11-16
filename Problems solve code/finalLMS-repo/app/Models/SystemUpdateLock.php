<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateLock extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'operation',
        'token',
        'locked_by',
        'acquired_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isActive(): bool
    {
        return $this->token !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
