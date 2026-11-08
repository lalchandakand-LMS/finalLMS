<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone_number', 'sms_marketing_opt_in', 'branch_id', 'account_status'])]
#[Hidden(['password', 'remember_token', 'phone_verification_code'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone_number', 'sms_marketing_opt_in', 'branch_id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function admissionProfile(): HasOne
    {
        return $this->hasOne(StudentAdmissionProfile::class);
    }

    public function guardiansOf(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'guardian_user_id');
    }

    public function studentGuardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'student_id');
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(StudentReferral::class, 'referrer_student_id');
    }

    public function referralsReceived(): HasOne
    {
        return $this->hasOne(StudentReferral::class, 'referred_student_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isBranchManager(): bool
    {
        return $this->role === 'branch_manager';
    }

    public function isAccountant(): bool
    {
        return $this->role === 'accountant';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_verification_expires_at' => 'datetime',
            'sms_marketing_opt_in' => 'boolean',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }
}
