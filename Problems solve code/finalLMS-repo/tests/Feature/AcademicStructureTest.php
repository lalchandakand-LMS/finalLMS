<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Branch;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_enrollment_is_recorded_against_the_selected_batch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Free Speaking Practice',
            'slug' => 'free-speaking-practice',
            'description' => 'Practice English conversation.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Saturday Practice',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($student)
            ->post(route('courses.enroll', $course), ['batch_id' => $batch->id])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
        ]);
    }

    public function test_admin_can_create_branch_and_batch_and_teacher_only_sees_assigned_batches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Core English skills.',
            'price' => 1500,
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.branches.store'), [
                'name' => 'Dhaka Center',
                'address' => 'Dhaka, Bangladesh',
                'phone' => '+8801700000000',
            ])
            ->assertRedirect(route('admin.batches.index'));

        $branchId = \Illuminate\Support\Facades\DB::table('branches')->value('id');

        $this->post(route('admin.batches.store'), [
            'branch_id' => $branchId,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Evening Batch 1',
            'capacity' => 25,
            'starts_at' => '2026-12-01 18:00',
            'ends_at' => '2027-02-01 20:00',
            'fee' => 5000,
            'status' => 'open',
        ])->assertRedirect(route('admin.batches.index'));

        $this->assertDatabaseHas('batches', [
            'title' => 'Evening Batch 1',
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'branch_id' => $branchId,
        ]);

        $this->get(route('admin.batches.index'))
            ->assertOk()
            ->assertSee('Branches and batches')
            ->assertSee('Edit batch')
            ->assertSee('Edit branch');

        $this->actingAs($teacher)
            ->get(route('teacher.batches.index'))
            ->assertOk()
            ->assertSee('Evening Batch 1');

        $this->actingAs($otherTeacher)
            ->get(route('teacher.batches.index'))
            ->assertOk()
            ->assertDontSee('Evening Batch 1');

        $this->actingAs($admin)
            ->put(route('admin.branches.update', $branchId), [
                'name' => 'Dhaka Main Center',
                'address' => 'Dhaka, Bangladesh',
                'phone' => '+8801700000000',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.batches.index'));

        $batchId = \Illuminate\Support\Facades\DB::table('batches')->value('id');
        $this->put(route('admin.batches.update', $batchId), [
            'branch_id' => $branchId,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Evening Batch 2',
            'capacity' => 30,
            'starts_at' => '2026-12-01 18:00',
            'ends_at' => '2027-02-01 20:00',
            'fee' => 5500,
            'status' => 'open',
        ])->assertRedirect(route('admin.batches.index'));

        $this->assertDatabaseHas('batches', ['id' => $batchId, 'title' => 'Evening Batch 2', 'fee' => '5500.00']);

        $this->delete(route('admin.batches.destroy', $batchId))->assertRedirect();
        $this->delete(route('admin.branches.destroy', $branchId))->assertRedirect();
        $this->assertDatabaseMissing('batches', ['id' => $batchId]);
        $this->assertDatabaseMissing('branches', ['id' => $branchId]);
    }
}
