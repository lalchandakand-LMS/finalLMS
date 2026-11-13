<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Validation\\ValidationException;

class GuardianStudentLink extends Model
{
    protected $fillable = [
        'guardian_id',
        'student_id',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $guardian = User::query()->find($link->guardian_id);
            $student = User::query()->find($link->student_id);

            if (!$guardian || !$guardian->isGuardian()) {
                throw ValidationException::withMessages(['guardian_id' => 'The linked account must have the guardian role.']);
            }

            if (!$student || $student->role !== 'student' || $student->id === $guardian->id) {
                throw ValidationException::withMessages(['student_id' => 'The linked account must be a different student account.']);
            }

            if (!in_array($link->status, ['pending', 'approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'The guardian relationship status is invalid.']);
            }

            if ($link->status === 'approved') {
                $approver = $link->approved_by ? User::query()->find($link->approved_by) : null;
                if (!$approver || !$approver->isAdmin() || !$link->approved_at) {
                    throw ValidationException::withMessages(['approved_by' => 'Only an administrator can approve a guardian relationship.']);
                }
            } elseif ($link->approved_by !== null || $link->approved_at !== null) {
                throw ValidationException::withMessages(['status' => 'Only approved relationships may contain approval metadata.']);
            }
        });
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved' && $this->approved_at !== null;
    }

    public function approveBy(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw ValidationException::withMessages(['approved_by' => 'Only an administrator can approve a guardian relationship.']);
        }

        $this->forceFill([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ])->save();
    }

    public function rejectBy(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw ValidationException::withMessages(['approved_by' => 'Only an administrator can reject a guardian relationship.']);
        }

        $this->forceFill([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
        ])->save();
    }
}
