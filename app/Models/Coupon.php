<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = ['code','discount_type','discount_value','minimum_order','starts_at','ends_at','usage_limit','per_user_limit','usage_count','is_active'];
    protected $casts = ['discount_value'=>'decimal:2','minimum_order'=>'decimal:2','starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean'];

    public function products(): BelongsToMany\n    {\n        return $this->belongsToMany(Product::class, 'coupon_product')->withTimestamps();\n    }\n\n    public function categories(): BelongsToMany\n    {\n        return $this->belongsToMany(ProductCategory::class, 'coupon_product_category')->withTimestamps();\n    }\n\n    public function redemptions(): HasMany
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

    public function eligibleSubtotal(iterable $items, ?int $userId = null): float\n    {\n        $productIds = $this->products()->pluck('products.id');\n        $categoryIds = $this->categories()->pluck('product_categories.id');\n        if ($productIds->isEmpty() && $categoryIds->isEmpty()) {\n            return collect($items)->sum(fn ($item) => (float) $item->product->currentPrice($userId) * $item->quantity);\n        }\n        $eligible = 0.0;\n        foreach ($items as $item) {\n            $product = $item->product;\n            if (!$product) continue;\n            $matches = $productIds->contains($product->id)\n                || ($categoryIds->isNotEmpty() && $product->categories()->whereIn('product_categories.id', $categoryIds)->exists());\n            if ($matches) $eligible += (float) $product->currentPrice($userId) * $item->quantity;\n        }\n        return round($eligible, 2);\n    }\n\n    public function calculateDiscount(float $subtotal): float
    {
        $amount = $this->discount_type === 'percentage'
            ? $subtotal * ((float)$this->discount_value / 100)
            : (float)$this->discount_value;
        return round(min($subtotal, max(0, $amount)), 2);
    }
}
