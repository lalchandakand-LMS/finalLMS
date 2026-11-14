<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_method', 'payment_reference',
        'payment_sender', 'payment_proof_path', 'currency', 'subtotal', 'discount_total',
        'total', 'paid_at', 'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'total' => 'decimal:2',
        'paid_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function refunds(): HasMany { return $this->hasMany(EbookRefund::class); }
}
