<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginVersionRecord extends Model
{
    protected $table = 'plugin_version_history';

    protected $fillable = [
        'plugin_key',
        'version',
        'package_sha256',
        'signature_key_id',
        'package_path',
        'status',
        'installed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
