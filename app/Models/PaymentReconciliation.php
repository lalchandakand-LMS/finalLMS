<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class PaymentReconciliation extends Model
{
    protected $fillable = [
        'payment_id', 'provider', 'gateway_payment_id', 'gateway_transaction_id', 'result',
        'local_status', 'gateway_status', 'local_amount', 'gateway_amount', 'currency', 'details', 'checked_at',
    ];

    protected $casts = ['local_amount' => 'decimal:2', 'gateway_amount' => 'decimal:2', 'checked_at' => 'datetime'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
