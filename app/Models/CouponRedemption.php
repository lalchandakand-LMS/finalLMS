<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'payment_id', 'discount_amount', 'status', 'redeemed_at'];

    protected $casts = ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime'];

    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
