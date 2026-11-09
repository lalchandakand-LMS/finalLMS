<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type','course_id','name','slug','sku','short_description','description','image_path',
        'seo_title','seo_description','regular_price','sale_price','currency','is_virtual',
        'is_downloadable','download_path','download_limit','download_expires_at','status',
        'featured','published_at','created_by',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_virtual' => 'boolean',
        'is_downloadable' => 'boolean',
        'featured' => 'boolean',
        'download_expires_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product')->withTimestamps();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function currentPrice(?int $userId = null): string
    {
        $regular = (float) $this->regular_price;
        $sale = $this->sale_price === null ? null : (float) $this->sale_price;
        $basePrice = $sale !== null && $sale >= 0 && $sale < $regular ? $sale : $regular;
        $flashSale = FlashSale::activeForProduct($this, $userId);
        return number_format($flashSale ? $flashSale->discountedPrice($basePrice) : $basePrice, 2, '.', '');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
