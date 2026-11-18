<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class PaymentGatewayEvent extends Model
{
    protected $fillable = [
        'provider', 'event_key', 'payment_id', 'gateway_payment_id', 'gateway_transaction_id',
        'event_type', 'verification_status', 'processing_status', 'payload_hash',
        'verified_payload', 'failure_reason', 'received_at', 'verified_at', 'processed_at',
    ];

    protected $casts = [
        'verified_payload' => 'array',
        'received_at' => 'datetime',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
