<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\GuardianStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianAttendanceReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_can_only_read_attendance_for_an_approved_linked_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $unlinkedStudent = User::factory()->create(['role' => 'student']);

        $branch = Branch::create([
            'name' => 'Guardian Attendance Branch',
            'slug' => 'guardian-attendance-branch',
        ]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Guardian Attendance Course',
            'slug' => 'guardian-attendance-course',
            'description' => 'Guardian attendance scope test.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Guardian Attendance Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $student->id,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $visible = Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);
        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $unlinkedStudent->id,
            'marked_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->withoutMiddleware([\App\Http\Middleware\RoleMiddleware::class])
            ->actingAs($guardian)
            ->getJson('/guardian/students/'.$student->id.'/summary')
            ->assertOk()
            ->assertJsonCount(1, 'data.attendance')
            ->assertJsonPath('data.attendance.0.id', $visible->id)
            ->assertJsonPath('data.attendance.0.status', 'present');

        $this->actingAs($guardian)
            ->getJson('/guardian/students/'.$unlinkedStudent->id.'/summary')
            ->assertNotFound();
    }
}
