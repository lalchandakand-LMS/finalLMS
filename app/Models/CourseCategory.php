<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'image_media_id', 'seo_title',
        'seo_description', 'is_visible', 'sort_order',
    ];

    protected $casts = ['is_visible' => 'boolean'];

    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_category_course')->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CourseContentPost::class, 'course_content_post_category');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
