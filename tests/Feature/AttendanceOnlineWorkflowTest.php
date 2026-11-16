<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceOnlineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_bulk_mark_attendance_with_location_and_audit_a_correction(): void
    {
        config(['attendance.allow_excused' => true]);
        [$teacher, $batch, $student] = $this->context();
        $url = route('teacher.batches.attendance.store', $batch);
        $payload = $this->payload($student, 'present');

        $this->actingAs($teacher)->post($url, $payload)->assertRedirect();
        $attendance = Attendance::query()->firstOrFail();

        $this->assertSame('present', $attendance->status);
        $this->assertSame('online', $attendance->source);
        $this->assertNotNull($attendance->latitude);
        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $attendance->id,
            'actor_id' => $teacher->id,
            'action' => 'marked',
            'new_status' => 'present',
        ]);

        $correction = $this->payload($student, 'late');
        unset($correction['records'][0]['reason']);
        $this->actingAs($teacher)->post($url, $correction)->assertStatus(422);
        $this->assertSame('present', $attendance->fresh()->status);

        $correction['records'][0]['reason'] = 'Teacher verified the late arrival.';
        $this->actingAs($teacher)->post($url, $correction)->assertRedirect();
        $this->assertSame('late', $attendance->fresh()->status);
        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $attendance->id,
            'actor_id' => $teacher->id,
            'action' => 'corrected',
            'old_status' => 'present',
            'new_status' => 'late',
            'reason' => 'Teacher verified the late arrival.',
        ]);
    }

    public function test_online_attendance_rejects_missing_or_stale_location(): void
    {
        [$teacher, $batch, $student] = $this->context();
        $payload = $this->payload($student, 'present');
        unset($payload['latitude']);

        $this->actingAs($teacher)->post(route('teacher.batches.attendance.store', $batch), $payload)
            ->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('attendances', 0);

        $payload = $this->payload($student, 'present');
        $payload['location_captured_at'] = now()->subHour()->toISOString();
        $this->actingAs($teacher)->post(route('teacher.batches.attendance.store', $batch), $payload)
            ->assertSessionHasErrors('location_captured_at');
        $this->assertDatabaseCount('attendances', 0);
    }

    private function context(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $branch = Branch::query()->create([
            'name' => 'Online Attendance Branch',
            'slug' => 'online-attendance-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batch = Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Attendance test batch',
            'capacity' => 25,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        return [$teacher, $batch, $student];
    }

    private function payload(User $student, string $status): array
    {
        return [
            'attendance_date' => now()->toDateString(),
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'location_accuracy_m' => 18,
            'location_captured_at' => now()->toISOString(),
            'records' => [[
                'student_id' => $student->id,
                'status' => $status,
            ]],
        ];
    }
}
