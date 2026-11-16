<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateBackup extends Model
{
    protected $fillable = [
        'preparation_id',
        'created_by',
        'code_snapshot_path',
        'code_snapshot_sha256',
        'code_snapshot_size',
        'database_backup_path',
        'database_backup_sha256',
        'database_backup_created_at',
        'status',
        'message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'code_snapshot_size' => 'integer',
            'database_backup_created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(SystemUpdatePreparation::class, 'preparation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
