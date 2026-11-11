<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAdmissionProfile extends Model
{
    protected $fillable = [
        'user_id', 'admission_number', 'referral_code', 'source_channel', 'source_details',
        'school_name', 'class_name', 'school_roll', 'interested_course_id', 'quoted_course_price',
        'course_updates_opt_in', 'privacy_consent_at', 'privacy_consent_version', 'current_address', 'current_post_office', 'current_district',
        'current_division', 'current_country', 'permanent_address', 'permanent_post_office',
        'permanent_district', 'permanent_division', 'permanent_country', 'living_type',
        'special_quota', 'special_quota_notes', 'admission_status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['course_updates_opt_in' => 'boolean', 'privacy_consent_at' => 'datetime', 'quoted_course_price' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function interestedCourse(): BelongsTo { return $this->belongsTo(Course::class, 'interested_course_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function guardians(): HasMany { return $this->hasMany(StudentGuardian::class, 'student_id', 'user_id'); }
    public function documents(): HasMany { return $this->hasMany(StudentDocument::class, 'student_id', 'user_id'); }
}
