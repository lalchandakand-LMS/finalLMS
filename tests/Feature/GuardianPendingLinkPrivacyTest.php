<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GuardianStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianPendingLinkPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_guardian_link_does_not_disclose_student_identity(): void
    {
        $guardian = User::factory()->create([
            'role' => 'guardian',
            'branch_id' => null,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'branch_id' => null,
            'email_verified_at' => now(),
            'name' => 'Private Learner Name',
            'email' => 'private.learner@example.test',
        ]);

        $this->actingAs($guardian)
            ->postJson('/guardian/links', [
                'student_email' => $student->email,
                'relationship' => 'Parent',
            ])
            ->assertAccepted()
            ->assertExactJson([
                'message' => 'If a verified student account matches that email, a request has been sent.',
            ]);

        $this->actingAs($guardian)
            ->getJson('/guardian/links')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.student', null)
            ->assertJsonMissingPath('data.0.student_id')
            ->assertDontSee($student->name)
            ->assertDontSee($student->email);

        $this->actingAs($guardian)
            ->get('/guardian/dashboard')
            ->assertOk()
            ->assertDontSee($student->name)
            ->assertDontSee($student->email)
            ->assertSee('Awaiting student approval');
    }

    public function test_guardian_link_request_does_not_reveal_whether_email_is_a_verified_student(): void
    {
        $guardian = User::factory()->create([
            'role' => 'guardian',
            'branch_id' => null,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'branch_id' => null,
            'email_verified_at' => now(),
            'email' => 'known.student@example.test',
        ]);

        $existingAccountResponse = $this->actingAs($guardian)->postJson('/guardian/links', [
            'student_email' => $student->email,
            'relationship' => 'Parent',
        ]);
        $missingAccountResponse = $this->actingAs($guardian)->postJson('/guardian/links', [
            'student_email' => 'missing.student@example.test',
            'relationship' => 'Parent',
        ]);

        $existingAccountResponse->assertAccepted();
        $missingAccountResponse->assertAccepted();
        $this->assertSame($existingAccountResponse->json(), $missingAccountResponse->json());
        $this->assertDatabaseCount('guardian_student_links', 1);
    }

    public function test_guardian_link_request_does_not_reveal_students_in_other_branches(): void
    {
        $branchA = Branch::query()->create([
            'name' => 'Privacy Branch A',
            'slug' => 'privacy-branch-a',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'name' => 'Privacy Branch B',
            'slug' => 'privacy-branch-b',
            'is_active' => true,
        ]);
        $guardian = User::factory()->create([
            'role' => 'guardian',
            'branch_id' => $branchA->id,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'branch_id' => $branchB->id,
            'email_verified_at' => now(),
            'email' => 'other.branch@example.test',
        ]);

        $otherBranchResponse = $this->actingAs($guardian)->postJson('/guardian/links', [
            'student_email' => $student->email,
            'relationship' => 'Parent',
        ]);
        $missingAccountResponse = $this->actingAs($guardian)->postJson('/guardian/links', [
            'student_email' => 'missing.branch@example.test',
            'relationship' => 'Parent',
        ]);

        $otherBranchResponse->assertAccepted();
        $missingAccountResponse->assertAccepted();
        $this->assertSame($otherBranchResponse->json(), $missingAccountResponse->json());
        $this->assertDatabaseCount('guardian_student_links', 0);
    }

    public function test_active_guardian_link_still_exposes_the_approved_student_identity(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        GuardianStudentLink::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $student->id,
            'requested_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($guardian)
            ->getJson('/guardian/links')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.student.id', $student->id)
            ->assertJsonPath('data.0.student.name', $student->name);
    }
}
