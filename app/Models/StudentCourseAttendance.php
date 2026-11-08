<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCourseAttendance extends Model
{
    protected $fillable = ['session_id','student_id','checked_in_at','checked_out_at','check_in_method','notes'];
    protected function casts(): array { return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime']; }
    public function session(): BelongsTo { return $this->belongsTo(StudentCourseSession::class, 'session_id'); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
