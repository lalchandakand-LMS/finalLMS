<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateEvent extends Model
{
    protected $fillable = [
        'preparation_id',
        'performed_by',
        'event',
        'status',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
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
