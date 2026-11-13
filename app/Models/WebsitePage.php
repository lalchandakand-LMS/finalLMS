<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class WebsitePage extends Model
{
    protected $fillable = [
        'title', 'slug', 'menu_label', 'template', 'sections', 'seo_title',
        'seo_description', 'show_in_menu', 'menu_order', 'status', 'published_at', 'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'show_in_menu' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
