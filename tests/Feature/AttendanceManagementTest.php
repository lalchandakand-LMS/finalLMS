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

    public function test_teacher_can_mark_attendance_for_batch_students_only(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
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
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

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
}
