<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginInstallation extends Model
{
    protected $table = 'plugin_registry';

    protected $fillable = [
        'plugin_key',
        'name',
        'version',
        'type',
        'manifest',
        'package_sha256',
        'signature_key_id',
        'package_path',
        'health_status',
        'health_message',
        'last_health_check_at',
        'installed_at',
        'activated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'metadata' => 'array',
            'signature_verified_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}
