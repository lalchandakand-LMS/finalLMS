<?php

namespace Tests\\Feature;

use App\\Models\\Attendance;
use App\\Models\\Batch;
use App\\Models\\Branch;
use App\\Models\\Course;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(User $teacher, string $title): Batch
    {
        $branch = Branch::create([
            'name' => $title.' Center',
            'slug' => strtolower(str_replace(' ', '-', $title)).'-center',
        ]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        return Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => $title,
            'capacity' => 25,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(2),
            'fee' => 0,
            'status' => 'open',
        ]);
    }

    public function test_teacher_attendance_report_is_limited_to_their_own_batches_and_supports_csv_export(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $ownBatch = $this->makeBatch($teacher, 'Teacher Own Batch');
        $otherBatch = $this->makeBatch($otherTeacher, 'Other Teacher Batch');

        Attendance::create([
            'batch_id' => $ownBatch->id, 'student_id' => $student->id,
            'marked_by' => $teacher->id, 'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);
        Attendance::create([
            'batch_id' => $otherBatch->id, 'student_id' => $student->id,
            'marked_by' => $otherTeacher->id, 'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->actingAs($teacher)->get(route('teacher.attendance-reports.index'))
            ->assertOk()->assertSee($ownBatch->title)->assertDontSee($otherBatch->title);
        $this->actingAs($teacher)->get(route('teacher.attendance-reports.export'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_student_cannot_access_staff_attendance_reports(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('teacher.attendance-reports.index'))->assertForbidden();
    }

    public function test_admin_attendance_report_filters_by_status_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, 'Admin Report Batch');

        Attendance::create([
            'batch_id' => $batch->id, 'student_id' => $student->id,
            'marked_by' => $teacher->id, 'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $this->actingAs($admin)->get(route('admin.attendance-reports.index', [
            'status' => 'present',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()->assertSee($student->name)->assertSee('Present');
    }
}
