<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone_number', 'sms_marketing_opt_in', 'branch_id'])]
#[Hidden(['password', 'remember_token', 'phone_verification_code'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone_number', 'sms_marketing_opt_in', 'branch_id'];

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

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

    /** Approved learner relationships visible to this guardian account. */
    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student_links', 'guardian_id', 'student_id')
            ->withPivot(['relationship', 'status', 'requested_by', 'reviewed_by', 'requested_at', 'reviewed_at'])
            ->withTimestamps()
            ->wherePivot('status', 'active')
            ->wherePivotNotNull('reviewed_at');
    }

    public function guardianStudentLinks(): HasMany
    {
        return $this->hasMany(GuardianStudentLink::class, 'guardian_id');
    }

    public function guardianLinksAsStudent(): HasMany
    {
        return $this->hasMany(GuardianStudentLink::class, 'student_id');
    }

    public function isGuardian(): bool
    {
        return $this->role === 'guardian';
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
            'password' => 'hashed',
            'role' => 'string',
        ];
    }
}
