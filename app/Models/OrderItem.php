<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id','product_id','flash_sale_id','product_type','resource_id','product_name','product_sku','quantity','regular_price','discount_total','line_total','product_snapshot'];
    protected $casts = ['regular_price' => 'decimal:2','discount_total' => 'decimal:2','line_total' => 'decimal:2','product_snapshot' => 'array'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
