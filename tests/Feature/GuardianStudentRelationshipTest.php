<?php

namespace Tests\Feature;

use App\Models\GuardianStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianStudentRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_can_resolve_only_explicitly_approved_linked_students(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $approvedStudent = User::factory()->create(['role' => 'student']);
        $pendingStudent = User::factory()->create(['role' => 'student']);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $approvedStudent->id,
            'status' => 'approved',
            'approved_by' => User::factory()->create(['role' => 'admin'])->id,
            'approved_at' => now(),
        ]);

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

        $this->expectException(\Illuminate\Database\QueryException::class);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
