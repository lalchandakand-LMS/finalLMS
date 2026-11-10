<?php

namespace Tests\Feature;

use App\Models\GuardianStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_verified_guardian_student_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)
            ->post(route('admin.guardians.links.store'), [
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'relationship' => 'mother',
                'notes' => 'Identity documents checked at the office.',
            ])
            ->assertRedirect(route('admin.guardians.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('guardian_student_links', [
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'mother',
            'status' => 'approved',
            'created_by' => $admin->id,
            'verified_by' => $admin->id,
        ]);

        $this->assertNotNull(GuardianStudentLink::query()->firstOrFail()->verified_at);
    }

    public function test_admin_cannot_link_an_account_with_the_wrong_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)
            ->post(route('admin.guardians.links.store'), [
                'guardian_id' => $teacher->id,
                'student_id' => $student->id,
                'relationship' => 'father',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('guardian_student_links', 0);
    }

    public function test_non_admin_cannot_manage_guardian_relationships(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $guardian = User::factory()->create(['role' => 'guardian']);

        $this->actingAs($student)
            ->get(route('admin.guardians.index'))
            ->assertForbidden();

        $this->actingAs($guardian)
            ->post(route('admin.guardians.links.store'), [
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'relationship' => 'father',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('guardian_student_links', 0);
    }

    public function test_guardian_can_view_multiple_verified_students_and_switch_between_them(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $firstStudent = User::factory()->create(['role' => 'student', 'name' => 'First Linked Student']);
        $secondStudent = User::factory()->create(['role' => 'student', 'name' => 'Second Linked Student']);

        foreach ([$firstStudent, $secondStudent] as $student) {
            GuardianStudentLink::create([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'relationship' => 'legal_guardian',
                'status' => 'approved',
                'created_by' => $guardian->id,
                'verified_by' => $guardian->id,
                'verified_at' => now(),
            ]);
        }

        $this->actingAs($guardian)
            ->get(route('dashboard'))
            ->assertRedirect(route('guardian.dashboard'));

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('First Linked Student')
            ->assertSee('Second Linked Student');

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard', ['student_id' => $secondStudent->id]))
            ->assertOk()
            ->assertSee('Second Linked Student');
    }

    public function test_guardian_cannot_view_an_unlinked_or_unverified_student(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $linkedStudent = User::factory()->create(['role' => 'student']);
        $unlinkedStudent = User::factory()->create(['role' => 'student']);
        $unverifiedStudent = User::factory()->create(['role' => 'student']);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $linkedStudent->id,
            'relationship' => 'father',
            'status' => 'approved',
            'verified_by' => $guardian->id,
            'verified_at' => now(),
        ]);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $unverifiedStudent->id,
            'relationship' => 'mother',
            'status' => 'approved',
            'verified_at' => null,
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard', ['student_id' => $unlinkedStudent->id]))
            ->assertForbidden();

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard', ['student_id' => $unverifiedStudent->id]))
            ->assertForbidden();
    }

    public function test_student_cannot_open_the_guardian_portal(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('guardian.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_revoke_a_guardian_link_and_access_disappears(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $link = GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'father',
            'status' => 'approved',
            'created_by' => $admin->id,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.guardians.links.revoke', $link))
            ->assertRedirect(route('admin.guardians.index'));

        $this->assertDatabaseHas('guardian_student_links', [
            'id' => $link->id,
            'status' => 'revoked',
            'revoked_by' => $admin->id,
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('No linked students yet')
            ->assertDontSee($student->name);
    }
}
