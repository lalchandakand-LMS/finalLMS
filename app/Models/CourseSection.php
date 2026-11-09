<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSection extends Model
{
    protected $fillable = ['course_id','title','description','position','status'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class,'section_id')->orderBy('order'); }
}
