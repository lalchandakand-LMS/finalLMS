<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_hash', 'device_name', 'user_agent', 'ip_address',
        'status', 'phone_otp_hash', 'phone_otp_expires_at', 'phone_verified_at',
        'approved_by', 'approved_at', 'rejected_at', 'last_used_at',
        'api_challenge_hash', 'api_challenge_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'phone_otp_expires_at' => 'datetime',
            'api_challenge_expires_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
