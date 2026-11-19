<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePage extends Model
{
    protected $fillable = [
        'title', 'slug', 'menu_label', 'template', 'sections', 'seo_title',
        'seo_description', 'show_in_menu', 'menu_order', 'status', 'published_at', 'created_by', 'academic_level_id',
    ];

    protected $casts = [
        'sections' => 'array',
        'show_in_menu' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsitePageRevision::class, 'website_page_id')->orderByDesc('version');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
