<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_student_profile_and_history_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->put(route('admin.students.profile.update', $student), [
            'student_number' => 'AEC-0002',
            'date_of_birth' => '2010-05-10',
            'blood_group' => 'B+',
            'admission_date' => '2026-01-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'student_number' => 'AEC-0002']);
        $this->assertDatabaseHas('student_lifecycle_events', ['student_id' => $student->id, 'event_type' => 'profile_updated']);
    }

    public function test_suspended_student_is_denied_student_role_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        StudentProfile::create(['user_id' => $student->id]);

        $this->actingAs($admin)->put(route('admin.students.suspend', $student), [
            'reason' => 'Temporary disciplinary suspension.',
        ])->assertRedirect();

        $this->actingAs($student)->get(route('student.courses.index'))->assertForbidden();
    }

    public function test_admin_can_reactivate_suspended_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        StudentProfile::create(['user_id' => $student->id, 'lifecycle_status' => 'suspended', 'suspended_at' => now()]);

        $this->actingAs($admin)->put(route('admin.students.reactivate', $student))->assertRedirect();
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'lifecycle_status' => 'active']);
    }
}
