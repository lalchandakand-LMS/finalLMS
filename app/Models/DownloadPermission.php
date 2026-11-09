<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadPermission extends Model
{
    protected $fillable = ['order_item_id','user_id','product_id','downloads_remaining','expires_at','revoked_at','download_count'];
    protected $casts = ['expires_at' => 'datetime','revoked_at' => 'datetime'];
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class)->withTrashed(); }
}
