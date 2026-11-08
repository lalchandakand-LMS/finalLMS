<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReferral extends Model
{
    protected $fillable = ['referrer_student_id','referred_student_id','referral_code','status','converted_at'];
    protected function casts(): array { return ['converted_at' => 'datetime']; }
    public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_student_id'); }
    public function referred(): BelongsTo { return $this->belongsTo(User::class, 'referred_student_id'); }
}
