<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiConnection extends Model
{
    protected $fillable = [
        'provider_key', 'display_name', 'branch_id', 'external_account_id',
        'credentials_encrypted', 'granted_scopes', 'capabilities', 'status',
        'webhook_verified', 'last_credential_check_at', 'last_webhook_event_at',
        'last_successful_operation_at', 'last_error', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'granted_scopes' => 'array',
            'capabilities' => 'array',
            'webhook_verified' => 'boolean',
            'last_credential_check_at' => 'datetime',
            'last_webhook_event_at' => 'datetime',
            'last_successful_operation_at' => 'datetime',
        ];
    }

    public function hasStoredCredentials(): bool
    {
        return filled($this->credentials_encrypted);
    }
}