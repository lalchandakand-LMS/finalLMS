<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiConnectorConnection extends Model
{
    protected $fillable = [
        'provider_key', 'branch_id', 'external_account_id', 'credentials',
        'granted_scopes', 'capabilities', 'status', 'webhook_verified_at',
        'last_checked_at', 'last_event_at', 'last_success_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'granted_scopes' => 'array',
            'capabilities' => 'array',
            'webhook_verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_event_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && $this->webhook_verified_at !== null;
    }
}
