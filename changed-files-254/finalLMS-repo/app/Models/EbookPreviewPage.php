<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookPreviewPage extends Model
{
    protected $fillable = [
        'product_id', 'page_number', 'file_path', 'alt_text', 'is_active', 'created_by',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
