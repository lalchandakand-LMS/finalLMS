<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCourseSession extends Model
{
    protected $fillable = ['course_id','batch_id','created_by','title','starts_at','ends_at','status'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function attendances(): HasMany { return $this->hasMany(StudentCourseAttendance::class, 'session_id'); }
}
