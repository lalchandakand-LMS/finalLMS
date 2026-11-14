<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_type', 'product_name', 'quantity',
        'regular_price', 'discount_total', 'line_total', 'product_snapshot',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2', 'discount_total' => 'decimal:2', 'line_total' => 'decimal:2',
        'product_snapshot' => 'array',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class)->withTrashed(); }
    public function downloadPermission(): HasOne { return $this->hasOne(DownloadPermission::class); }
}
