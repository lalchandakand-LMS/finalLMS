<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id', 'student_number', 'date_of_birth', 'gender', 'blood_group',
        'nationality', 'address_line', 'city', 'district', 'emergency_contact_name',
        'emergency_contact_phone', 'previous_school', 'admission_date',
        'lifecycle_status', 'suspended_at', 'suspended_by', 'suspension_reason',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'admission_date' => 'date', 'suspended_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }
}
