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

    public function courses(): BelongsToMany { return $this->belongsToMany(Course::class)->withTimestamps(); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isAvailableFor(?int $userId = null): bool
    {
        if ($this->status !== 'active' || $this->starts_at->isFuture() || $this->ends_at->isPast()) return false;
        if ($this->max_redemptions !== null && $this->payments()->whereIn('status', ['pending','approved'])->count() >= $this->max_redemptions) return false;
        if ($userId !== null && $this->payments()->where('user_id',$userId)->whereIn('status',['pending','approved'])->count() >= $this->per_student_limit) return false;
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
            ->whereHas('courses', fn ($q) => $q->where('courses.id',$courseId))
            ->orderByDesc('priority')->orderBy('ends_at')->get()
            ->first(fn (self $sale) => $sale->isAvailableFor($userId));
    }

    public static function activeCampaigns(): \Illuminate\Support\Collection
    {
        return static::query()->where('status','active')->where('starts_at','<=',now())->where('ends_at','>=',now())
            ->whereHas('courses', fn ($q) => $q->where('courses.status','published'))
            ->with(['courses' => fn ($q) => $q->where('courses.status','published')->with('teacher')])
            ->orderByDesc('priority')->orderBy('ends_at')->get()
            ->filter(fn (self $sale) => $sale->isAvailableFor())->values();
    }
}
