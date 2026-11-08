<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class CommunityPost extends Model
{
    protected $fillable = ['user_id', 'body', 'visibility', 'attachment_path', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityPostComment::class, 'post_id');
    }
}
