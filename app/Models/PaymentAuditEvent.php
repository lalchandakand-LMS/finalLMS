<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAuditEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment audit events are append-only.'));
    }

    protected $fillable = [
        'payment_id', 'invoice_payment_id', 'invoice_refund_id', 'ebook_refund_id', 'actor_id', 'provider',
        'event', 'source', 'correlation_id', 'before_state', 'after_state', 'ip_address',
    ];

    protected $casts = ['before_state' => 'array', 'after_state' => 'array'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function ebookRefund(): BelongsTo
    {
        return $this->belongsTo(EbookRefund::class, 'ebook_refund_id');
    }

    public function invoiceRefund(): BelongsTo
    {
        return $this->belongsTo(InvoiceRefund::class, 'invoice_refund_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
