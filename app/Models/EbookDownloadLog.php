<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookDownloadLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'download_permission_id', 'user_id', 'product_id',
        'action', 'ip_address', 'user_agent', 'downloaded_at',
    ];

    protected $casts = ['downloaded_at' => 'datetime'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(DownloadPermission::class, 'download_permission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
