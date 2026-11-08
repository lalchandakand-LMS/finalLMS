<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGuardian extends Model
{
    protected $fillable = ['student_id','guardian_user_id','guardian_type','relationship','name','mobile_number','email','occupation','nid_number','is_primary','contact_consent'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'contact_consent' => 'boolean', 'nid_number' => 'encrypted']; }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function account(): BelongsTo { return $this->belongsTo(User::class, 'guardian_user_id'); }
    public function documents(): HasMany { return $this->hasMany(StudentDocument::class, 'guardian_id'); }
}
