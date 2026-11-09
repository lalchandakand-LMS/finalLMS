<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercePaymentTransaction extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'invoice', 'gateway_payment_id',
        'gateway_transaction_id', 'amount', 'currency', 'status',
        'gateway_response', 'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'verified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
