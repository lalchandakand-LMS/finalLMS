<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdvancedStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_student_profile_and_history_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->put(route('admin.students.profile.update', $student), [
            'student_number' => 'AEC-0002', 'date_of_birth' => '2010-05-10',
            'blood_group' => 'B+', 'admission_date' => '2026-01-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'student_number' => 'AEC-0002']);
        $this->assertDatabaseHas('student_lifecycle_events', ['student_id' => $student->id, 'event_type' => 'profile_updated', 'actor_id' => $admin->id]);
    }

    public function test_suspension_and_graduation_are_audited_and_block_student_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        StudentProfile::create(['user_id' => $student->id]);

        $this->actingAs($admin)->put(route('admin.students.suspend', $student), ['reason' => 'Temporary disciplinary suspension.'])->assertRedirect();
        $this->assertDatabaseHas('student_lifecycle_events', ['student_id' => $student->id, 'event_type' => 'suspended']);
        $this->actingAs($student)->get(route('student.courses.index'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.students.reactivate', $student))->assertRedirect();
        $this->actingAs($admin)->put(route('admin.students.graduate', $student), ['reason' => 'Completed the full academic programme.'])->assertRedirect();
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'lifecycle_status' => 'graduated']);
        $this->actingAs($student)->get(route('student.courses.index'))->assertForbidden();
    }

    public function test_admin_can_view_student_directory_and_profile_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        StudentProfile::create(['user_id' => $student->id, 'student_number' => 'AEC-0003']);

        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk()->assertSee($student->name);
        $this->actingAs($admin)->get(route('admin.students.show', $student))->assertOk()->assertSee('AEC-0003')->assertSee('Student History');
    }

    public function test_branch_manager_cannot_view_students_from_another_branch(): void
    {
        $branchA = Branch::query()->create(['name' => 'Branch A', 'slug' => 'branch-a', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'slug' => 'branch-b', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id, 'admin_permissions' => ['branch.students']]);
        $own = User::factory()->create(['role' => 'student', 'branch_id' => $branchA->id]);
        $other = User::factory()->create(['role' => 'student', 'branch_id' => $branchB->id]);

        $this->actingAs($manager)->get(route('branch.students.manage'))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get(route('branch.students.manage.show', $other))->assertNotFound();
    }

    public function test_csv_import_requires_existing_student_and_confirm_writes_audited_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'email' => 'import.student@example.com']);
        $csv = "email,student_number,academic_level\nimport.student@example.com,AEC-CSV-01,Level 2\nmissing@example.com,AEC-CSV-02,Level 1\n";

        $this->actingAs($admin)->post(route('admin.students.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertRedirect()->assertSessionHas('student_import_summary');

        $this->post(route('admin.students.import-confirm'))->assertRedirect();
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'student_number' => 'AEC-CSV-01', 'academic_level' => 'Level 2']);
        $this->assertDatabaseHas('student_lifecycle_events', ['student_id' => $student->id, 'event_type' => 'bulk_profile_imported']);
    }

    public function test_csv_export_is_available_to_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student', 'name' => 'Exportable Learner']);
        $response = $this->actingAs($admin)->get(route('admin.students.export'));
        $response->assertOk();
        $this->assertStringContainsString('Exportable Learner', $response->streamedContent());
    }
}
