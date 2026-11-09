<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coupon extends Model
{
    protected $fillable = ['code','discount_type','discount_value','minimum_order','starts_at','ends_at','usage_limit','per_user_limit','usage_count','is_active'];
    protected $casts = ['discount_value'=>'decimal:2','minimum_order'=>'decimal:2','starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product')->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'coupon_product_category')->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isAvailableFor(int $userId, float $subtotal): bool
    {
        if (!$this->is_active || ($this->starts_at && $this->starts_at->isFuture()) || ($this->ends_at && $this->ends_at->isPast())) return false;
        if ($subtotal < (float)$this->minimum_order) return false;
        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) return false;
        if ($this->redemptions()->where('user_id',$userId)->count() >= $this->per_user_limit) return false;
        return true;
    }

    public function eligibleSubtotal(iterable $items, ?int $userId = null, array $prices = []): float
    {
        $productIds = $this->products()->pluck('products.id');
        $categoryIds = $this->categories()->pluck('product_categories.id');
        if ($productIds->isEmpty() && $categoryIds->isEmpty()) {
            return collect($items)->sum(fn ($item) => (float) ($prices[$item->product_id] ?? $item->product->currentPrice($userId)) * $item->quantity);
        }
        $eligible = 0.0;
        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) continue;
            $matches = $productIds->contains($product->id)
                || ($categoryIds->isNotEmpty() && $product->categories()->whereIn('product_categories.id', $categoryIds)->exists());
            if ($matches) $eligible += (float) ($prices[$product->id] ?? $product->currentPrice($userId)) * $item->quantity;
        }
        return round($eligible, 2);
    }

    public function calculateDiscount(float $subtotal): float
    {
        $amount = $this->discount_type === 'percentage'
            ? $subtotal * ((float)$this->discount_value / 100)
            : (float)$this->discount_value;
        return round(min($subtotal, max(0, $amount)), 2);
    }
}
