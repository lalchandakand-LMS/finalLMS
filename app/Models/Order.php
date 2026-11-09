<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['order_number','user_id','coupon_id','status','payment_method','payment_reference','currency','subtotal','discount_total','tax_total','total','customer_snapshot','paid_at','completed_at'];
    protected $casts = ['customer_snapshot' => 'array','subtotal' => 'decimal:2','discount_total' => 'decimal:2','tax_total' => 'decimal:2','total' => 'decimal:2','paid_at' => 'datetime','completed_at' => 'datetime'];

    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
