<?php

declare(strict_types=1);

namespace App\Modules\Admissions;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AdmissionApplication extends Model
{
    protected $table = 'admission_applications';

    protected $fillable = [
        'branch_id', 'application_code', 'applicant_name', 'email', 'phone',
        'guardian_phone', 'desired_course', 'preferred_batch', 'previous_education',
        'date_of_birth', 'address', 'application_source', 'status',
        'document_verification_status', 'reviewer_notes', 'reviewed_by',
        'reviewed_at', 'interview_at', 'assigned_to', 'created_by',
        'converted_student_id', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'interview_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class, 'admission_application_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdmissionApplicationEvent::class, 'admission_application_id')->latest();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
