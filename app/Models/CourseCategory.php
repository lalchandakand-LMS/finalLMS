<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'image_path', 'seo_title',
        'seo_description', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_category_course')->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_course_category')->withTimestamps();
    }
}
