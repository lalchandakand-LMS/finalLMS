<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuditEvent extends Model
{
    protected $fillable = [
        'payment_id', 'invoice_payment_id', 'invoice_refund_id', 'actor_id', 'provider',
        'event', 'source', 'correlation_id', 'before_state', 'after_state', 'ip_address',
    ];

    protected $casts = ['before_state' => 'array', 'after_state' => 'array'];

    public function invoiceRefund(): BelongsTo
    {
        return $this->belongsTo(InvoiceRefund::class, 'invoice_refund_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
