<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdatePreparation extends Model
{
    protected $fillable = [
        'prepared_by',
        'version',
        'package_sha256',
        'manifest_sha256',
        'package_path',
        'file_size',
        'status',
        'message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
