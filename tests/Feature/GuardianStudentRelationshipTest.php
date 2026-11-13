<?php

namespace Tests\\Feature;

use App\\Models\\GuardianStudentLink;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Validation\\ValidationException;
use Tests\\TestCase;

class GuardianStudentRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_can_resolve_only_explicitly_approved_linked_students(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $approvedStudent = User::factory()->create(['role' => 'student']);
        $pendingStudent = User::factory()->create(['role' => 'student']);

        $approvedLink = GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $approvedStudent->id,
            'status' => 'pending',
        ]);
        $approvedLink->approveBy(User::factory()->create(['role' => 'admin']));

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $pendingStudent->id,
            'status' => 'pending',
        ]);

        $this->assertSame(
            [$approvedStudent->id],
            $guardian->linkedStudents()->pluck('users.id')->all()
        );
        $this->assertTrue($guardian->isGuardian());
        $this->assertFalse($approvedStudent->isGuardian());
    }

    public function test_guardian_relationships_are_unique_and_cascade_with_user_deletion(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $this->expectException(\\Illuminate\\Database\\QueryException::class);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_admin_cannot_approve_or_reject_a_guardian_relationship(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $link = GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        try {
            $link->approveBy($teacher);
            $this->fail('A teacher must not approve guardian relationships.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('guardian_student_links', [
                'id' => $link->id,
                'status' => 'pending',
                'approved_by' => null,
            ]);
        }
    }

    public function test_relationship_rejects_wrong_account_roles_and_unverified_approval_metadata(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $anotherStudent = User::factory()->create(['role' => 'student']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        try {
            GuardianStudentLink::create([
                'guardian_id' => $student->id,
                'student_id' => $anotherStudent->id,
                'status' => 'pending',
            ]);
            $this->fail('A student account must not be accepted as a guardian.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('guardian_student_links', 0);
        }

        try {
            GuardianStudentLink::create([
                'guardian_id' => $guardian->id,
                'student_id' => $teacher->id,
                'status' => 'pending',
            ]);
            $this->fail('A teacher account must not be linked as a student.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('guardian_student_links', 0);
        }

        try {
            GuardianStudentLink::create([
                'guardian_id' => $guardian->id,
                'student_id' => $anotherStudent->id,
                'status' => 'approved',
                'approved_by' => $teacher->id,
                'approved_at' => now(),
            ]);
            $this->fail('A non-admin approver must not be accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('guardian_student_links', 0);
        }
    }

    public function test_admin_can_reject_pending_guardian_relationship(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $link = GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $link->rejectBy($admin);

        $this->assertDatabaseHas('guardian_student_links', [
            'id' => $link->id,
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $this->assertCount(0, $guardian->fresh()->linkedStudents);
    }
}
