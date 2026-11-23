<?php

namespace App\Modules\Carousel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carousel extends Model
{
    protected $table = 'website_carousels';

    protected $fillable = [
        'title', 'slug', 'source_type', 'item_limit', 'desktop_cards',
        'mobile_cards', 'autoplay', 'interval_ms', 'show_arrows', 'show_dots',
        'status', 'sort_order', 'starts_at', 'ends_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'autoplay' => 'boolean',
            'show_arrows' => 'boolean',
            'show_dots' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CarouselItem::class, 'carousel_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Return safe display records from a manually curated or live LMS source.
     *
     * @return \Illuminate\Support\Collection<int, array{title:string,description:string,image_url:?string,link_url:?string}>
     */
    public function displayItems()
    {
        $limit = max(1, min(30, (int) $this->item_limit));

        if ($this->source_type === 'courses') {
            return \App\Models\Course::query()->where('status', 'published')->latest()->limit($limit)->get()
                ->map(fn ($course) => [
                    'title' => (string) $course->title,
                    'description' => (string) ($course->short_description ?: $course->description ?: ''),
                    'image_url' => $course->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) : null,
                    'link_url' => route('courses.show', $course),
                ]);
        }

        if ($this->source_type === 'teachers') {
            return \App\Models\User::query()->where('role', 'teacher')->orderBy('name')->limit($limit)->get()
                ->map(fn ($teacher) => [
                    'title' => (string) $teacher->name,
                    'description' => 'Instructor',
                    'image_url' => null,
                    'link_url' => null,
                ]);
        }

        if ($this->source_type === 'testimonials') {
            return \App\Models\Comment::query()->with('author')
                ->where('status', 'approved')
                ->where('commentable_type', \App\Models\Course::class)
                ->whereIn('commentable_id', \App\Models\Course::query()->where('status', 'published')->select('id'))
                ->latest()->limit($limit)->get()
                ->map(fn ($comment) => [
                    'title' => (string) ($comment->author?->name ?: 'Student'),
                    'description' => (string) $comment->body,
                    'image_url' => null,
                    'link_url' => null,
                ]);
        }

        return $this->items()->limit($limit)->get()->map(fn (CarouselItem $item) => [
            'title' => (string) $item->title,
            'description' => (string) ($item->description ?? ''),
            'image_url' => $item->image_url,
            'link_url' => $item->link_url,
        ]);
    }
}