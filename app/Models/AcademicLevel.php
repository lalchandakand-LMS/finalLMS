<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class AcademicLevel extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(WebsitePage::class);
    }
}
