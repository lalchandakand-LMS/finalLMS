<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseContentPost extends Model
{
    protected $fillable = [
        'author_id', 'thumbnail_media_id', 'thumbnail_path', 'title', 'slug',
        'short_description', 'long_description', 'video_url', 'content_file_path',
        'content_file_name', 'content_file_mime', 'content_file_size', 'access_type',
        'regular_price', 'sale_price', 'academic_level_id', 'product_id',
        'seo_title', 'seo_description', 'tags', 'status', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
        'content_file_size' => 'integer',
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CourseContentPostVersion::class, 'course_content_post_id')->orderByDesc('version_number');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CourseCategory::class, 'course_content_post_category')
            ->orderBy('course_categories.sort_order')
            ->orderBy('course_categories.name');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_curriculum_items')
            ->withPivot(['section_title', 'position', 'is_preview', 'duration_minutes'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function currentPrice(): float
    {
        $regular = (float) ($this->regular_price ?? 0);
        $sale = $this->sale_price === null ? null : (float) $this->sale_price;

        return $sale !== null && $sale >= 0 && $sale < $regular ? $sale : $regular;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function supportsVideoUrl(string $url): bool
    {
        return (new self(['video_url' => $url]))->video_embed_url !== null
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    public function getVideoEmbedUrlAttribute(): ?string
    {
        $url = (string) $this->video_url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = explode('/', $path)[0] ?? '';
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = $query['v'] ?? '';
            if (preg_match('#^(?:embed|shorts|live)/([A-Za-z0-9_-]{11})$#', $path, $matches)) {
                $id = $matches[1];
            }
            return preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#(?:video/)?([0-9]{6,12})$#', $path, $matches)) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }
}
