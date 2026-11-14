<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'name', 'slug', 'short_description', 'description', 'image_path',
        'regular_price', 'sale_price', 'currency', 'is_downloadable', 'download_path',
        'download_limit', 'download_expires_at', 'status', 'published_at', 'created_by',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_downloadable' => 'boolean',
        'download_expires_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product')->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function currentPrice(): string
    {
        $regular = (float) $this->regular_price;
        $sale = $this->sale_price === null ? null : (float) $this->sale_price;

        return number_format($sale !== null && $sale >= 0 && $sale < $regular ? $sale : $regular, 2, '.', '');
    }

    public function scopePublished($query)
    {
        return $query->where('type', 'ebook')
            ->where('status', 'published')
            ->where('is_downloadable', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
