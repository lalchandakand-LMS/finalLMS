<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginVersionHistory extends Model
{
    protected $table = 'plugin_version_history';

    public $timestamps = false;

    protected $fillable = [
        'plugin_key',
        'version',
        'package_sha256',
        'signature_key_id',
        'action',
        'status',
        'actor_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
