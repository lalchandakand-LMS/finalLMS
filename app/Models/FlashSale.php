<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    protected $fillable = ['title','badge','description','banner_path','discount_type','discount_value','starts_at','ends_at','status','max_redemptions','per_student_limit','priority','created_by'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'discount_value' => 'decimal:2'];

    public function courses(): BelongsToMany { return $this->belongsToMany(Course::class, 'flash_sale_course')->withTimestamps(); }
    public function categories(): BelongsToMany { return $this->belongsToMany(CourseCategory::class, 'flash_sale_course_category')->withTimestamps(); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
    public function productCategories(): BelongsToMany { return $this->belongsToMany(ProductCategory::class, 'flash_sale_product_category')->withTimestamps(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isAvailableFor(?int $userId = null): bool
    {
        if ($this->status !== 'active' || $this->starts_at->isFuture() || $this->ends_at->isPast()) return false;
        $paidRedemptions = fn () => $this->payments()->whereIn('status', ['pending', 'approved']);
        $freeRedemptions = fn () => $this->enrollments()->whereIn('status', ['active', 'completed']);
        $commerceRedemptions = fn () => $this->orderItems()->whereHas('order', fn ($orders) => $orders->whereIn('status', ['pending', 'completed']));
        $redemptionCount = $paidRedemptions()->count() + $freeRedemptions()->count()
            + $commerceRedemptions()->distinct('order_id')->count('order_id');
        if ($this->max_redemptions !== null && $redemptionCount >= $this->max_redemptions) return false;

        if ($userId !== null) {
            $studentRedemptions = $paidRedemptions()->where('user_id', $userId)->count()
                + $freeRedemptions()->where('user_id', $userId)->count()
                + $commerceRedemptions()->whereHas('order', fn ($orders) => $orders->where('user_id', $userId))->distinct('order_id')->count('order_id');
            if ($studentRedemptions >= $this->per_student_limit) return false;
        }
        return true;
    }

    public function discountedPrice(float $basePrice): float
    {
        $price = $this->discount_type === 'percentage'
            ? $basePrice * (1 - ((float) $this->discount_value / 100))
            : min($basePrice, (float) $this->discount_value);
        return round(max(0, $price), 2);
    }

    public static function activeForCourse(int $courseId, ?int $userId = null): ?self
    {
        return static::query()->where('status','active')->where('starts_at','<=',now())->where('ends_at','>=',now())
            ->where(function ($query) use ($courseId): void {
                $query->whereHas('courses', fn ($courses) => $courses->where('courses.id', $courseId))
                    ->orWhereHas('categories.posts', fn ($posts) => $posts->published()
                        ->whereHas('courses', fn ($courses) => $courses->where('courses.id', $courseId)->where('courses.status', 'published')));
            })
            ->orderByDesc('priority')->orderBy('ends_at')->get()
            ->first(fn (self $sale) => $sale->isAvailableFor($userId));
    }

    public static function activeForProduct(Product $product, ?int $userId = null): ?self
    {
        $categoryIds = $product->categories()->pluck('product_categories.id');
        if (! $product->course_id && $categoryIds->isEmpty()) {
            return null;
        }

        return static::query()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($query) use ($product, $categoryIds): void {
                if ($product->course_id) {
                    $query->whereHas('courses', fn ($courses) => $courses->where('courses.id', $product->course_id));
                    if ($categoryIds->isNotEmpty()) {
                        $query->orWhereHas('productCategories', fn ($categories) => $categories->whereIn('product_categories.id', $categoryIds));
                    }
                } elseif ($categoryIds->isNotEmpty()) {
                    $query->whereHas('productCategories', fn ($categories) => $categories->whereIn('product_categories.id', $categoryIds));
                }
            })
            ->orderByDesc('priority')
            ->orderBy('ends_at')
            ->get()
            ->first(fn (self $sale) => $sale->isAvailableFor($userId));
    }

    public static function activeCampaigns(): \Illuminate\Support\Collection
    {
        return static::query()->where('status','active')->where('starts_at','<=',now())->where('ends_at','>=',now())
            ->where(function ($query): void {
                $query->whereHas('courses', fn ($courses) => $courses->where('courses.status', 'published'))
                    ->orWhereHas('categories.posts', fn ($posts) => $posts->published()
                        ->whereHas('courses', fn ($courses) => $courses->where('courses.status', 'published')));
            })
            ->with([
                'courses' => fn ($courses) => $courses->where('courses.status', 'published')->with('teacher'),
                'categories.posts' => fn ($posts) => $posts->published()->with([
                    'courses' => fn ($courses) => $courses->where('courses.status', 'published')->with('teacher'),
                ]),
            ])
            ->orderByDesc('priority')->orderBy('ends_at')->get()
            ->filter(fn (self $sale) => $sale->isAvailableFor())
            ->each(function (self $sale): void {
                $categoryCourses = $sale->categories->flatMap(fn ($category) => $category->posts->flatMap(fn ($post) => $post->courses));
                $sale->setRelation('displayCourses', $sale->courses->merge($categoryCourses)->unique('id')->values());
            })->values();
    }
}
