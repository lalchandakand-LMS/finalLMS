<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogPost extends Model
{
    protected $fillable = ['author_id', 'cover_media_id', 'title', 'slug', 'excerpt', 'category', 'tags', 'body', 'video_url', 'status', 'published_at', 'seo_title', 'seo_description'];

    protected $casts = ['published_at' => 'datetime', 'tags' => 'array'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function courseSections(): BelongsToMany
    {
        return $this->belongsToMany(CourseSection::class, 'course_section_blog_post')->withPivot(['position','attached_by'])->withTimestamps();
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }
}
