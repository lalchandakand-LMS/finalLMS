<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseSection extends Model
{
    protected $fillable = ['course_id','title','description','position','status'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function blogPosts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'course_section_blog_post')->withPivot(['position','attached_by'])->withTimestamps()->orderByPivot('position');
    }

    public function lessons(): HasMany { return $this->hasMany(Lesson::class,'section_id')->orderBy('order'); }
}
