<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdatePreflight extends Model
{
    protected $fillable = [
        'preparation_id',
        'performed_by',
        'status',
        'checks',
        'summary',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(SystemUpdatePreparation::class, 'preparation_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
