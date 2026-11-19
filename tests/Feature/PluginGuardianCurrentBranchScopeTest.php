<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GuardianStudentLink;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginGuardianCurrentBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_plugin_access_is_revoked_when_linked_student_changes_branch_or_branch_is_inactive(): void
    {
        $branchA = Branch::query()->create(['name' => 'Guardian Branch A', 'slug' => 'guardian-branch-a', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Guardian Branch B', 'slug' => 'guardian-branch-b', 'is_active' => true]);
        $guardian = User::factory()->create(['role' => 'guardian', 'branch_id' => $branchA->id]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branchA->id]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batch = Batch::query()->create([
            'branch_id' => $branchA->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Guardian Scope Batch',
            'capacity' => 30,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonths(2),
            'fee' => 3000,
            'status' => 'open',
        ]);
        $enrollment = Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        GuardianStudentLink::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $student->id,
            'requested_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);

        $access = app(PluginResourceAccess::class);
        $this->assertTrue($access->allows($guardian, $student));
        $this->assertTrue($access->allows($guardian, $course));
        $this->assertTrue($access->allows($guardian, $batch));
        $this->assertTrue($access->allows($guardian, $enrollment));

        // Reassigning either side after approval must invalidate the old link.
        $guardian->update(['branch_id' => $branchB->id]);
        $this->assertFalse($access->allows($guardian, $student));
        $this->assertSame([], $access->scopeQuery(User::query(), $guardian)->pluck('id')->all());
        $guardian->update(['branch_id' => $branchA->id]);

        // A later student branch transfer must invalidate the previously approved link
        // for plugin resources immediately, without waiting for link cleanup.
        $student->update(['branch_id' => $branchB->id]);
        $this->assertFalse($access->allows($guardian, $student));
        $this->assertFalse($access->allows($guardian, $course));
        $this->assertFalse($access->allows($guardian, $batch));
        $this->assertFalse($access->allows($guardian, $enrollment));
        $this->assertSame([], $access->scopeQuery(User::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Course::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Batch::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Enrollment::query(), $guardian)->pluck('id')->all());

        $student->update(['branch_id' => $branchA->id]);
        $student->update(['role' => 'teacher']);
        $this->assertFalse($access->allows($guardian, $course));
        $this->assertFalse($access->allows($guardian, $batch));
        $this->assertFalse($access->allows($guardian, $enrollment));
        $this->assertSame([], $access->scopeQuery(Course::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Batch::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Enrollment::query(), $guardian)->pluck('id')->all());
        $student->update(['role' => 'student']);

        $branchA->update(['is_active' => false]);
        $this->assertFalse($access->allows($guardian, $student));
        $this->assertFalse($access->allows($guardian, $course));
        $this->assertFalse($access->allows($guardian, $batch));
        $this->assertFalse($access->allows($guardian, $enrollment));
        $this->assertSame([], $access->scopeQuery(User::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Course::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Batch::query(), $guardian)->pluck('id')->all());
        $this->assertSame([], $access->scopeQuery(Enrollment::query(), $guardian)->pluck('id')->all());
    }
}
