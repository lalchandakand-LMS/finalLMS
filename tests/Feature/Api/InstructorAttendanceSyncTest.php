<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceSyncReview;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstructorAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(User $teacher, User $student, string $enrollmentStatus = 'active'): Batch
    {
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Offline Attendance Course',
            'slug' => 'offline-attendance-course-'.$teacher->id,
            'description' => 'Feature test course.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $branch = Branch::create([
            'name' => 'Offline Attendance Branch '.$teacher->id,
            'slug' => 'offline-attendance-branch-'.$teacher->id,
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Offline Test Batch',
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
            'status' => $enrollmentStatus,
        ]);

        return $batch;
    }

    private function payload(Batch $batch, User $student, ?string $key = null, string $status = 'present'): array
    {
        return [
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => $status,
            'location' => [
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'accuracy_meters' => 12.5,
                'captured_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function test_offline_sync_persists_attendance_and_is_idempotent(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $payload = $this->payload($batch, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'duplicate');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'idempotency_key' => $payload['idempotency_key'],
            'location_latitude' => 23.8103,
        ]);
        $this->assertDatabaseCount('attendance_change_logs', 1);
        $this->assertDatabaseHas('attendance_change_logs', [
            'new_status' => 'present',
            'source' => 'offline_sync',
            'actor_id' => $teacher->id,
        ]);
    }

    public function test_sync_returns_conflict_without_overwriting_existing_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $payload = $this->payload($batch, $student, null, 'absent');

        \App\Models\Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => $payload['attendance_date'],
            'status' => 'present',
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonStructure(['results' => [['attendance_id']]]);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }

    public function test_sync_rejects_future_attendance_dates(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $payload = $this->payload($batch, $student);
        $payload['attendance_date'] = now()->addDay()->toDateString();

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.0.message', 'Attendance cannot be recorded for a future date.');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_sync_rejects_unassigned_batch_and_inactive_enrollment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherBatch = $this->makeBatch($otherTeacher, $student);
        $inactiveStudent = User::factory()->create(['role' => 'student']);
        $inactiveBatch = $this->makeBatch($teacher, $inactiveStudent, 'completed');

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', [
                'records' => [
                    $this->payload($otherBatch, $student),
                    $this->payload($inactiveBatch, $inactiveStudent),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.1.status', 'failed');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_non_teacher_mobile_identity_cannot_sync_attendance(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->withoutMiddleware()
            ->actingAs($student)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => []])
            ->assertForbidden();
    }

    public function test_sync_rejects_invalid_foreground_location_metadata(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $payload = $this->payload($batch, $student);
        $payload['location']['latitude'] = 95;

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed');

        $this->assertDatabaseCount('attendances', 0);
    }
    public function test_stale_capture_can_be_sent_to_admin_review_and_approved_with_audit(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        Setting::setValue('attendance_max_location_age_minutes', '1');
        Setting::setValue('attendance_stale_location_action', 'manual_review');
        $payload = $this->payload($batch, $student);
        $payload['location']['captured_at'] = now()->subMinutes(2)->toIso8601String();

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'needs_review');

        $review = AttendanceSyncReview::query()->where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
        $this->assertDatabaseCount('attendances', 0);

        $this->actingAs($admin)
            ->post('/admin/attendance-sync-reviews/'.$review->id.'/resolve', [
                'decision' => 'approve',
                'reason' => 'Checked the classroom register and confirmed the record.',
            ])
            ->assertRedirect(route('admin.attendance-sync-reviews.index'));

        $this->assertDatabaseHas('attendance_sync_reviews', [
            'id' => $review->id,
            'review_status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'idempotency_key' => $payload['idempotency_key'],
            'student_id' => $student->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_change_logs', [
            'actor_id' => $admin->id,
            'source' => 'stale_location_manual_review',
            'reason' => 'Checked the classroom register and confirmed the record.',
        ]);

        $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'duplicate');
    }

    public function test_admin_can_reject_stale_attendance_review(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        Setting::setValue('attendance_max_location_age_minutes', '1');
        Setting::setValue('attendance_stale_location_action', 'manual_review');
        $payload = $this->payload($batch, $student);
        $payload['location']['captured_at'] = now()->subMinutes(2)->toIso8601String();

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'needs_review');
        $review = AttendanceSyncReview::query()->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/attendance-sync-reviews/'.$review->id.'/resolve', [
                'decision' => 'reject',
                'reason' => 'Capture could not be verified against the classroom register.',
            ])
            ->assertRedirect(route('admin.attendance-sync-reviews.index'));

        $this->assertDatabaseHas('attendance_sync_reviews', [
            'id' => $review->id,
            'review_status' => 'rejected',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_sync_enforces_configured_maximum_location_age(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        Setting::setValue('attendance_max_location_age_minutes', '1');
        $payload = $this->payload($batch, $student);
        $payload['location']['captured_at'] = now()->subMinutes(2)->toIso8601String();

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.0.message', 'Location capture exceeds the institution maximum age policy.');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_excused_status_requires_institution_policy_and_is_allowed_when_enabled(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $payload = $this->payload($batch, $student, null, 'excused');

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed');
        $this->assertDatabaseCount('attendances', 0);

        Setting::setValue('attendance_allow_excused', '1');
        $payload = $this->payload($batch, $student, null, 'excused');

        $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', ['records' => [$payload]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');
        $this->assertDatabaseHas('attendances', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'excused',
        ]);
    }

    public function test_conflict_resolution_requires_reason_and_writes_immutable_audit_event(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $attendance = \App\Models\Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])
            ->actingAs($teacher)
            ->patchJson('/api/v1/instructor/attendance/'.$attendance->id.'/resolve', [
                'status' => 'present',
                'reason' => 'Verified with the signed classroom register.',
            ])
            ->assertOk()
            ->assertJsonPath('attendance.status', 'present');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_change_logs', [
            'attendance_id' => $attendance->id,
            'actor_id' => $teacher->id,
            'previous_status' => 'absent',
            'new_status' => 'present',
            'reason' => 'Verified with the signed classroom register.',
            'source' => 'offline_conflict_resolution',
        ]);
    }

    public function test_signed_qr_attendance_is_short_lived_scoped_and_replay_safe(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $session = $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->assertGreaterThan(0, $session->json('data.expires_in_seconds'));
        $this->assertLessThanOrEqual(120, $session->json('data.expires_in_seconds'));
        $signedUrl = $session->json('data.check_in_url');
        $checkInPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);
        $location = [
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'accuracy_meters' => 12.5,
            'captured_at' => now()->toIso8601String(),
        ];

        $this->actingAs($student)
            ->postJson($checkInPath, $location)
            ->assertCreated()
            ->assertJsonPath('data.status', 'synced');

        $this->actingAs($student)
            ->postJson($checkInPath, $location)
            ->assertOk()
            ->assertJsonPath('data.status', 'duplicate');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_qr_checkins', 1);
        $this->assertDatabaseHas('attendance_change_logs', [
            'attendance_id' => \App\Models\Attendance::query()->value('id'),
            'actor_id' => $student->id,
            'source' => 'qr',
            'new_status' => 'present',
        ]);
    }

    public function test_qr_session_cannot_be_created_for_another_attendance_date(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/attendance/qr', [
                'attendance_date' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('attendance_qr_sessions', 0);
    }

    public function test_qr_checkin_requires_fresh_location_captured_during_active_session(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $session = $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $signedUrl = $session->json('data.check_in_url');
        $checkInPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);
        $this->actingAs($student)
            ->postJson($checkInPath, [
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'accuracy_meters' => 12.5,
                'captured_at' => now()->subMinutes(3)->toIso8601String(),
            ])
            ->assertUnprocessable();

        $this->actingAs($student)
            ->postJson($checkInPath, [
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'accuracy_meters' => 12.5,
                'captured_at' => now()->addMinute()->toIso8601String(),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('attendance_qr_checkins', 0);
    }

    public function test_expired_signed_qr_session_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $session = $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $signedUrl = $session->json('data.check_in_url');
        $checkInPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);

        \Illuminate\Support\Facades\DB::table('attendance_qr_sessions')
            ->where('public_id', $session->json('data.session_id'))
            ->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($student)
            ->postJson($checkInPath, [
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'accuracy_meters' => 12.5,
                'captured_at' => now()->toIso8601String(),
            ])
            ->assertGone();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_signed_qr_rejects_tampered_nonce_and_inactive_enrollment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student, 'completed');

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $session = $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $signedUrl = $session->json('data.check_in_url');
        $checkInPath = parse_url($signedUrl, PHP_URL_PATH).'?'.parse_url($signedUrl, PHP_URL_QUERY);
        $location = [
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'accuracy_meters' => 12.5,
            'captured_at' => now()->toIso8601String(),
        ];

        $tamperedPath = preg_replace('/nonce=[^&]+/', 'nonce=invalid', $checkInPath);
        $this->actingAs($student)->postJson($tamperedPath, $location)->assertForbidden();

        $this->actingAs($student)->postJson($checkInPath, $location)->assertForbidden();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_qr_session_creation_is_limited_to_the_assigned_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherBatch = $this->makeBatch($otherTeacher, $student);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);

        $this->actingAs($teacher)
            ->postJson('/api/v1/instructor/batches/'.$otherBatch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson('/api/v1/instructor/batches/'.$otherBatch->id.'/attendance/qr', [
                'attendance_date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendance_qr_sessions', 0);
    }

    public function test_offline_sync_rejects_a_batch_outside_the_teachers_branch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $otherBranch = Branch::create([
            'name' => 'Different teacher branch',
            'slug' => 'different-teacher-branch-'.$teacher->id,
        ]);
        $teacher->update(['branch_id' => $otherBranch->id]);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])->actingAs($teacher)
            ->postJson('/api/v1/instructor/attendance/sync', [
                'records' => [$this->payload($batch, $student)],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'failed')
            ->assertJsonPath('results.0.message', 'Batch is not assigned to this instructor.');

        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('attendance_change_logs', 0);
        $this->assertDatabaseCount('attendance_sync_reviews', 0);
    }

    public function test_conflict_resolution_rejects_a_batch_outside_the_teachers_branch_without_mutation(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $batch = $this->makeBatch($teacher, $student);
        $attendance = \App\Models\Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
        ]);
        $otherBranch = Branch::create([
            'name' => 'Conflict resolution other branch',
            'slug' => 'conflict-resolution-other-branch-'.$teacher->id,
        ]);
        $teacher->update(['branch_id' => $otherBranch->id]);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateApiToken::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])->actingAs($teacher)
            ->patchJson('/api/v1/instructor/attendance/'.$attendance->id.'/resolve', [
                'status' => 'present',
                'reason' => 'Verified with the signed classroom register.',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'absent',
            'marked_by' => $teacher->id,
        ]);
        $this->assertDatabaseCount('attendance_change_logs', 0);
    }

}
