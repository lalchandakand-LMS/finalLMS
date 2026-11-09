<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GuardianStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_can_view_only_students_linked_by_admin(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Guardian visible course']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $link = GuardianStudent::create(['guardian_id' => $guardian->id, 'student_id' => $student->id]);

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertDontSee($otherStudent->name);

        $this->get(route('guardian.children.show', $link))
            ->assertOk()
            ->assertSee('Guardian visible course');

        $foreignLink = GuardianStudent::create(['guardian_id' => User::factory()->create(['role' => 'guardian'])->id, 'student_id' => $otherStudent->id]);
        $this->get(route('guardian.children.show', $foreignLink))->assertNotFound();
    }

    public function test_unlinked_guardian_is_sent_to_dashboard_and_sees_no_child_data(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);

        $this->actingAs($guardian)
            ->get(route('dashboard'))
            ->assertRedirect(route('guardian.dashboard'));

        $this->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('No student linked yet');
    }

    public function test_admin_can_link_and_unlink_guardian_and_student_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)
            ->post(route('admin.guardians.link'), ['guardian_id' => $guardian->id, 'student_id' => $student->id])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('guardian_student', ['guardian_id' => $guardian->id, 'student_id' => $student->id]);

        $this->delete(route('admin.guardians.unlink', [$guardian, $student]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('guardian_student', ['guardian_id' => $guardian->id, 'student_id' => $student->id]);
    }

    public function test_non_admin_cannot_link_guardian_accounts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($teacher)
            ->post(route('admin.guardians.link'), ['guardian_id' => $guardian->id, 'student_id' => $student->id])
            ->assertForbidden();

        $this->assertDatabaseCount('guardian_student', 0);
    }
}
