<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_mark_attendance_for_active_batch_students_only(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Active Student']);
        $completedStudent = User::factory()->create(['role' => 'student', 'name' => 'Completed Student']);
        $cancelledStudent = User::factory()->create(['role' => 'student', 'name' => 'Cancelled Student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Core English skills.',
            'price' => 1500,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Evening Batch 1',
            'capacity' => 25,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(2),
            'fee' => 5000,
            'status' => 'open',
        ]);
        foreach ([
            [$student, 'active'],
            [$completedStudent, 'completed'],
            [$cancelledStudent, 'cancelled'],
        ] as [$enrolledUser, $status]) {
            Enrollment::create([
                'user_id' => $enrolledUser->id,
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'status' => $status,
            ]);
        }

        $this->actingAs($teacher)
            ->get(route('teacher.batches.attendance.index', $batch))
            ->assertOk()
            ->assertSee('Active Student')
            ->assertDontSee('Completed Student')
            ->assertDontSee('Cancelled Student');

        $this->actingAs($teacher)
            ->post(route('teacher.batches.attendance.store', $batch), [
                'attendance_date' => now()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'present']],
            ])
            ->assertRedirect(route('teacher.batches.attendance.index', [
                'batch' => $batch,
                'date' => now()->toDateString(),
            ]));

        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        foreach ([$completedStudent, $cancelledStudent] as $inactiveStudent) {
            $this->actingAs($teacher)
                ->post(route('teacher.batches.attendance.store', $batch), [
                    'attendance_date' => now()->toDateString(),
                    'records' => [['student_id' => $inactiveStudent->id, 'status' => 'present']],
                ])
                ->assertForbidden();
        }

        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherBatch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Attendance::create([
            'batch_id' => $otherBatch->id,
            'student_id' => $otherStudent->id,
            'marked_by' => $otherTeacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->actingAs($student)
            ->get(route('student.attendance.index'))
            ->assertOk()
            ->assertSee('Evening Batch 1')
            ->assertDontSee('Other Batch');

        $this->actingAs($otherTeacher)
            ->get(route('teacher.batches.attendance.index', $batch))
            ->assertForbidden();
    }
    public function test_changing_saved_attendance_requires_reason_and_records_audit_history(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Attendance Correction Course',
            'slug' => 'attendance-correction-course',
            'description' => 'Correction audit test.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Correction Center', 'slug' => 'correction-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Correction Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        Attendance::create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.batches.attendance.store', $batch), [
                'attendance_date' => now()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'present']],
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.batches.attendance.store', $batch), [
                'attendance_date' => now()->toDateString(),
                'records' => [[
                    'student_id' => $student->id,
                    'status' => 'present',
                    'reason' => 'Checked against the signed register.',
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_change_logs', [
            'actor_id' => $teacher->id,
            'previous_status' => 'absent',
            'new_status' => 'present',
            'reason' => 'Checked against the signed register.',
            'source' => 'web',
        ]);
    }

    public function test_branch_manager_can_mark_attendance_only_for_an_authorized_assigned_branch(): void
    {
        $branch = Branch::create(['name' => 'Branch Attendance Center', 'slug' => 'branch-attendance-center']);
        $otherBranch = Branch::create(['name' => 'Other Attendance Center', 'slug' => 'other-attendance-center']);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'admin_permissions' => ['branch.attendance'],
        ]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Branch Attendance Course',
            'slug' => 'branch-attendance-course',
            'description' => 'Branch scope test.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assigned Branch Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        $otherBatch = Batch::create([
            'branch_id' => $otherBranch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Other Branch Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        foreach ([[$batch, $student], [$otherBatch, $otherStudent]] as [$enrolledBatch, $enrolledStudent]) {
            Enrollment::create([
                'user_id' => $enrolledStudent->id,
                'course_id' => $course->id,
                'batch_id' => $enrolledBatch->id,
                'status' => 'active',
            ]);
        }

        $this->actingAs($manager)
            ->get(route('admin.branch-workspace.batches.attendance.index', $batch))
            ->assertOk()
            ->assertSee('Assigned Branch Batch');

        $this->actingAs($manager)
            ->post(route('admin.branch-workspace.batches.attendance.store', $batch), [
                'attendance_date' => now()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'present']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.branch-workspace.batches.attendance.index', $otherBatch))
            ->assertForbidden();
    }

}