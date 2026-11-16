<?php

namespace App\Services\Plugins;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class PluginResourceAccess
{
    public function allows(User $actor, Model $resource): bool
    {
        $supportedResource = $resource instanceof User
            || $resource instanceof Course
            || $resource instanceof Batch
            || $resource instanceof Enrollment;

        if (! $supportedResource) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        return match (true) {
            $resource instanceof User => $this->allowsStudent($actor, $resource),
            $resource instanceof Course => $this->allowsCourse($actor, $resource),
            $resource instanceof Batch => $this->allowsBatch($actor, $resource),
            $resource instanceof Enrollment => $this->allowsEnrollment($actor, $resource),
            default => false,
        };
    }

    public function authorize(User $actor, Model $resource): void
    {
        abort_unless($this->allows($actor, $resource), 404, 'Resource not found.');
    }

    private function allowsStudent(User $actor, User $student): bool
    {
        if ((int) $actor->id === (int) $student->id) {
            return true;
        }

        if (! $student->isStudent()) {
            return false;
        }

        if ($actor->isStudent()) {
            return false;
        }

        if ($actor->isGuardian()) {
            return $actor->linkedStudents()->whereKey($student->id)->exists();
        }

        if ($actor->isBranchManager()) {
            return $this->hasActiveBranch($actor)
                && $actor->branch_id !== null
                && (int) $student->branch_id === (int) $actor->branch_id;
        }

        if ($actor->isTeacher()) {
            return Enrollment::query()
                ->where('user_id', $student->id)
                ->where(function ($query) use ($actor): void {
                    $query->whereHas('course', fn ($course) => $course->where('teacher_id', $actor->id))
                        ->orWhereHas('batch', fn ($batch) => $batch->where('teacher_id', $actor->id));
                })
                ->exists();
        }

        return false;
    }

    private function allowsCourse(User $actor, Course $course): bool
    {
        if ($actor->isTeacher()) {
            return (int) $course->teacher_id === (int) $actor->id
                || Batch::query()->where('course_id', $course->id)->where('teacher_id', $actor->id)->exists();
        }

        if ($actor->isBranchManager()) {
            return $this->hasActiveBranch($actor)
                && $actor->branch_id !== null
                && Batch::query()->where('course_id', $course->id)->where('branch_id', $actor->branch_id)->exists();
        }

        if ($actor->isStudent()) {
            return Enrollment::query()->where('user_id', $actor->id)->where('course_id', $course->id)->exists();
        }

        if ($actor->isGuardian()) {
            return Enrollment::query()
                ->where('course_id', $course->id)
                ->whereIn('user_id', $actor->linkedStudents()->select('users.id'))
                ->exists();
        }

        return false;
    }

    private function allowsBatch(User $actor, Batch $batch): bool
    {
        if ($actor->isTeacher()) {
            return (int) $batch->teacher_id === (int) $actor->id;
        }

        if ($actor->isBranchManager()) {
            return $this->hasActiveBranch($actor)
                && $actor->branch_id !== null
                && (int) $batch->branch_id === (int) $actor->branch_id;
        }

        if ($actor->isStudent()) {
            return Enrollment::query()->where('user_id', $actor->id)->where('batch_id', $batch->id)->exists();
        }

        if ($actor->isGuardian()) {
            return Enrollment::query()
                ->where('batch_id', $batch->id)
                ->whereIn('user_id', $actor->linkedStudents()->select('users.id'))
                ->exists();
        }

        return false;
    }

    private function allowsEnrollment(User $actor, Enrollment $enrollment): bool
    {
        if ($actor->isStudent()) {
            return (int) $enrollment->user_id === (int) $actor->id;
        }

        if ($actor->isGuardian()) {
            return $actor->linkedStudents()->whereKey($enrollment->user_id)->exists();
        }

        if ($actor->isBranchManager()) {
            return $this->hasActiveBranch($actor)
                && $actor->branch_id !== null
                && $enrollment->batch_id !== null
                && Batch::query()->whereKey($enrollment->batch_id)->where('branch_id', $actor->branch_id)->exists();
        }

        if ($actor->isTeacher()) {
            return ($enrollment->batch_id !== null
                    && Batch::query()->whereKey($enrollment->batch_id)->where('teacher_id', $actor->id)->exists())
                || Course::query()->whereKey($enrollment->course_id)->where('teacher_id', $actor->id)->exists();
        }

        return false;
    }

    private function hasActiveBranch(User $actor): bool
    {
        return $actor->branch_id !== null
            && Branch::query()->whereKey($actor->branch_id)->where('is_active', true)->exists();
    }
}
