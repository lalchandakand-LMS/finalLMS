<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_sync_offline_attendance_and_replay_is_idempotent(): void
    {
        [$teacher, $batch, $student, $token] = $this->attendanceContext();
        $payload = ['records' => [$this->record($batch, $student)]];

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', $payload)
            ->assertOk()
            ->assertJsonPath('data.0.status', 'synced')
            ->assertJsonPath('summary.synced', 1);

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', $payload)
            ->assertOk()
            ->assertJsonPath('data.0.status', 'synced')
            ->assertJsonPath('summary.synced', 1);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_sync_operations', 1);
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
            'source' => 'offline_sync',
        ]);
        $this->assertDatabaseHas('attendance_audits', [
            'actor_id' => $teacher->id,
            'action' => 'offline_synced',
            'new_status' => 'present',
        ]);
    }

    public function test_offline_sync_reports_existing_server_record_as_conflict_without_overwriting(): void
    {
        [$teacher, $batch, $student, $token] = $this->attendanceContext();
        \App\Models\Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => '2026-11-16',
            'status' => 'absent',
        ]);

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', [
            'records' => [$this->record($batch, $student, 'present')],
        ])->assertOk()
            ->assertJsonPath('data.0.status', 'conflict')
            ->assertJsonPath('data.0.server_status', 'absent')
            ->assertJsonPath('data.0.client_status', 'present');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);
    }

    public function test_offline_sync_rejects_missing_location_and_unassigned_batch(): void
    {
        [$teacher, $batch, $student, $token] = $this->attendanceContext();
        $record = $this->record($batch, $student);
        unset($record['latitude']);

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', [
            'records' => [$record],
        ])->assertUnprocessable()->assertJsonValidationErrors('records.0.latitude');

        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherToken = $this->tokenFor($otherTeacher);
        $this->withToken($otherToken)->postJson('/api/v1/instructor/attendance/sync', [
            'records' => [$this->record($batch, $student)],
        ])->assertOk()->assertJsonPath('data.0.code', 'batch_not_authorized');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_reusing_an_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        [$teacher, $batch, $student, $token] = $this->attendanceContext();
        $record = $this->record($batch, $student);

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', [
            'records' => [$record],
        ])->assertOk()->assertJsonPath('data.0.status', 'synced');

        $record['status'] = 'late';
        $this->withToken($token)->postJson('/api/v1/instructor/attendance/sync', [
            'records' => [$record],
        ])->assertOk()->assertJsonPath('data.0.code', 'idempotency_key_reused');

        $this->assertDatabaseCount('attendances', 1);
    }


    public function test_teacher_can_create_short_lived_signed_qr_and_student_checkin_is_replay_safe(): void
    {
        [$teacher, $batch, $student, $teacherToken] = $this->attendanceContext();
        $studentToken = $this->tokenFor($student);

        $session = $this->withToken($teacherToken)->postJson(
            '/api/v1/instructor/batches/'.$batch->id.'/attendance/qr',
            ['attendance_date' => '2026-11-16']
        )->assertCreated()
            ->assertJsonPath('data.expires_in_seconds', fn ($value) => $value <= 120 && $value > 0);

        $url = $session->json('data.check_in_url');
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $payload = [
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'location_accuracy_m' => 12,
            'location_captured_at' => now()->toISOString(),
        ];

        $this->withToken($studentToken)->postJson($path, $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'synced');

        $this->withToken($studentToken)->postJson($path, $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'duplicate');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_qr_checkins', 1);
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
            'source' => 'qr',
        ]);
    }

    public function test_teacher_can_resolve_an_offline_conflict_with_reason_and_audit(): void
    {
        [$teacher, $batch, $student, $token] = $this->attendanceContext();
        $attendance = \App\Models\Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => '2026-11-16',
            'status' => 'absent',
        ]);

        $this->withToken($token)->postJson('/api/v1/instructor/attendance/'.$attendance->id.'/resolve-conflict', [
            'status' => 'present',
            'reason' => 'Verified against class register.',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'location_accuracy_m' => 16,
            'location_captured_at' => now()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.previous_status', 'absent')
            ->assertJsonPath('data.current_status', 'present');

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => 'present']);
        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $attendance->id,
            'actor_id' => $teacher->id,
            'action' => 'conflict_resolved',
            'old_status' => 'absent',
            'new_status' => 'present',
            'reason' => 'Verified against class register.',
        ]);
    }

    private function attendanceContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'slug' => 'main-branch-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batch = Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning batch',
            'capacity' => 30,
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

        return [$teacher, $batch, $student, $this->tokenFor($teacher)];
    }

    private function tokenFor(User $teacher): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::query()->create([
            'user_id' => $teacher->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Attendance test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::query()->create([
            'user_id' => $teacher->id,
            'student_device_id' => $device->id,
            'name' => 'Attendance test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }

    private function record(Batch $batch, User $student, string $status = 'present'): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-11-16',
            'status' => $status,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'location_accuracy_m' => 18.5,
            'location_captured_at' => now()->toISOString(),
        ];
    }
}
