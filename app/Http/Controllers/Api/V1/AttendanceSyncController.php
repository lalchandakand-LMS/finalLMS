<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceSyncController extends Controller
{
    /**
     * Accept a bounded set of offline attendance operations.
     * The server remains authoritative; conflicting records are never overwritten.
     */
    public function sync(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isTeacher(), 403, 'Only authenticated teachers can sync attendance.');

        $data = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.idempotency_key' => ['required', 'uuid', 'distinct'],
            'records.*.batch_id' => ['required', 'integer', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'min:1'],
            'records.*.attendance_date' => ['required', 'date_format:Y-m-d'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'records.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'records.*.location_accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'records.*.location_captured_at' => ['required', 'date'],
        ]);

        $results = [];
        foreach ($data['records'] as $record) {
            $results[] = DB::transaction(function () use ($actor, $record): array {
                $payloadHash = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                $previousOperation = DB::table('attendance_sync_operations')
                    ->where('actor_id', $actor->id)
                    ->where('idempotency_key', $record['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($previousOperation) {
                    if (! hash_equals($previousOperation->payload_hash, $payloadHash)) {
                        return [
                            'idempotency_key' => $record['idempotency_key'],
                            'status' => 'failed',
                            'code' => 'idempotency_key_reused',
                            'message' => 'This idempotency key was already used for a different payload.',
                        ];
                    }

                    return json_decode($previousOperation->result_payload, true, 512, JSON_THROW_ON_ERROR);
                }

                $batch = Batch::query()->find($record['batch_id']);
                if (! $batch || (int) $batch->teacher_id !== (int) $actor->id
                    || ($actor->branch_id !== null && (int) $batch->branch_id !== (int) $actor->branch_id)) {
                    return $this->rememberResult($actor->id, $record, $payloadHash, [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => 'failed',
                        'code' => 'batch_not_authorized',
                        'message' => 'You are not assigned to this batch or branch.',
                    ]);
                }

                $student = User::query()->whereKey($record['student_id'])->where('role', 'student')->first();
                if (! $student || ($student->branch_id !== null && (int) $student->branch_id !== (int) $batch->branch_id)) {
                    return $this->rememberResult($actor->id, $record, $payloadHash, [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => 'failed',
                        'code' => 'student_out_of_scope',
                        'message' => 'The student is outside the batch branch.',
                    ]);
                }

                $enrolled = $batch->enrollments()
                    ->where('user_id', $student->id)
                    ->where('status', 'active')
                    ->exists();

                if (! $enrolled) {
                    return $this->rememberResult($actor->id, $record, $payloadHash, [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => 'failed',
                        'code' => 'enrollment_inactive',
                        'message' => 'The student is not actively enrolled in this batch.',
                    ]);
                }

                if ($record['status'] === 'excused' && ! config('attendance.allow_excused', false)) {
                    return $this->rememberResult($actor->id, $record, $payloadHash, [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => 'failed',
                        'code' => 'excused_status_disabled',
                        'message' => 'Excused attendance is disabled by institutional policy.',
                    ]);
                }

                $capturedAt = Carbon::parse($record['location_captured_at']);
                if ($capturedAt->greaterThan(now()->addMinutes(5))) {
                    return $this->rememberResult($actor->id, $record, $payloadHash, [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => 'failed',
                        'code' => 'invalid_location_timestamp',
                        'message' => 'The location capture time cannot be in the future.',
                    ]);
                }

                $existing = Attendance::query()
                    ->where('batch_id', $batch->id)
                    ->where('student_id', $student->id)
                    ->whereDate('attendance_date', $record['attendance_date'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $result = [
                        'idempotency_key' => $record['idempotency_key'],
                        'status' => $existing->status === $record['status'] ? 'duplicate' : 'conflict',
                        'code' => $existing->status === $record['status'] ? 'already_recorded' : 'attendance_conflict',
                        'attendance_id' => $existing->id,
                        'server_status' => $existing->status,
                        'client_status' => $record['status'],
                        'message' => $existing->status === $record['status']
                            ? 'The same attendance is already recorded.'
                            : 'A different server attendance exists. Resolve it explicitly; no data was overwritten.',
                    ];

                    return $this->rememberResult($actor->id, $record, $payloadHash, $result);
                }

                $attendance = Attendance::query()->create([
                    'batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'marked_by' => $actor->id,
                    'attendance_date' => $record['attendance_date'],
                    'status' => $record['status'],
                    'latitude' => $record['latitude'],
                    'longitude' => $record['longitude'],
                    'location_accuracy_m' => $record['location_accuracy_m'],
                    'location_captured_at' => $capturedAt,
                    'source' => 'offline_sync',
                ]);

                DB::table('attendance_audits')->insert([
                    'attendance_id' => $attendance->id,
                    'actor_id' => $actor->id,
                    'action' => 'offline_synced',
                    'old_status' => null,
                    'new_status' => $record['status'],
                    'reason' => null,
                    'metadata' => json_encode([
                        'idempotency_key' => $record['idempotency_key'],
                        'location_accuracy_m' => $record['location_accuracy_m'],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->rememberResult($actor->id, $record, $payloadHash, [
                    'idempotency_key' => $record['idempotency_key'],
                    'status' => 'synced',
                    'code' => 'accepted',
                    'attendance_id' => $attendance->id,
                    'server_status' => $attendance->status,
                    'received_at' => now()->toISOString(),
                ]);
            });
        }

        return response()->json([
            'data' => $results,
            'summary' => [
                'synced' => collect($results)->where('status', 'synced')->count(),
                'duplicate' => collect($results)->where('status', 'duplicate')->count(),
                'conflict' => collect($results)->where('status', 'conflict')->count(),
                'failed' => collect($results)->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function resolveConflict(Request $request, Attendance $attendance): JsonResponse
    {
        $teacher = $request->user();
        $batch = $attendance->batch;
        abort_unless($teacher?->isTeacher() && $batch
            && (int) $batch->teacher_id === (int) $teacher->id
            && ($teacher->branch_id === null || (int) $teacher->branch_id === (int) $batch->branch_id), 403);

        $data = $request->validate([
            'status' => ['required', 'in:present,absent,late,excused'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'location_captured_at' => ['required', 'date'],
        ]);

        if ($data['status'] === 'excused' && ! config('attendance.allow_excused', false)) {
            throw ValidationException::withMessages(['status' => 'Excused attendance is disabled by institutional policy.']);
        }

        $capturedAt = Carbon::parse($data['location_captured_at']);
        abort_if($capturedAt->greaterThan(now()->addMinutes(5)), 422, 'Invalid location capture time.');

        return DB::transaction(function () use ($attendance, $teacher, $data, $capturedAt): JsonResponse {
            $row = Attendance::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $activeEnrollment = $row->batch->enrollments()
                ->where('user_id', $row->student_id)
                ->where('status', 'active')
                ->exists();
            abort_unless($activeEnrollment, 403, 'The student is no longer actively enrolled.');

            $oldStatus = $row->status;
            $row->fill([
                'status' => $data['status'],
                'marked_by' => $teacher->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'location_accuracy_m' => $data['location_accuracy_m'],
                'location_captured_at' => $capturedAt,
                'source' => 'conflict_resolution',
            ])->save();

            DB::table('attendance_audits')->insert([
                'attendance_id' => $row->id,
                'actor_id' => $teacher->id,
                'action' => 'conflict_resolved',
                'old_status' => $oldStatus,
                'new_status' => $row->status,
                'reason' => $data['reason'],
                'metadata' => json_encode(['source' => 'offline_sync'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'status' => 'resolved',
                    'attendance_id' => $row->id,
                    'previous_status' => $oldStatus,
                    'current_status' => $row->status,
                ],
            ]);
        });
    }

    public function createQrSession(Request $request, Batch $batch): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher?->isTeacher()
            && (int) $batch->teacher_id === (int) $teacher->id
            && ($teacher->branch_id === null || (int) $teacher->branch_id === (int) $batch->branch_id), 403);

        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $publicId = (string) Str::uuid();
        $nonce = (string) Str::uuid();
        $expiresAt = now()->addSeconds(min(120, max(30, (int) config('attendance.qr_session_ttl_seconds', 120))));

        DB::table('attendance_qr_sessions')->insert([
            'public_id' => $publicId,
            'batch_id' => $batch->id,
            'created_by' => $teacher->id,
            'attendance_date' => $data['attendance_date'],
            'nonce_hash' => hash('sha256', $nonce),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checkInUrl = URL::temporarySignedRoute(
            'api.v1.attendance.qr.checkin',
            $expiresAt,
            ['session' => $publicId, 'nonce' => $nonce]
        );

        return response()->json([
            'data' => [
                'session_id' => $publicId,
                'check_in_url' => $checkInUrl,
                'expires_at' => $expiresAt->toISOString(),
                'expires_in_seconds' => now()->diffInSeconds($expiresAt),
            ],
        ], 201);
    }

    public function checkInQr(Request $request, string $session): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'location_captured_at' => ['required', 'date'],
        ]);

        $qrSession = DB::table('attendance_qr_sessions')
            ->where('public_id', $session)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($qrSession, 410, 'This QR attendance session has expired or was revoked.');

        $nonce = (string) $request->query('nonce', '');
        abort_unless($nonce !== '' && hash_equals($qrSession->nonce_hash, hash('sha256', $nonce)), 403);

        $capturedAt = Carbon::parse($data['location_captured_at']);
        abort_if($capturedAt->greaterThan(now()->addMinutes(5)), 422, 'Invalid location capture time.');

        return DB::transaction(function () use ($student, $qrSession, $data, $capturedAt): JsonResponse {
            $batch = Batch::query()->findOrFail($qrSession->batch_id);
            abort_unless($student->branch_id === null || (int) $student->branch_id === (int) $batch->branch_id, 403, 'This batch is outside your branch.');

            $enrolled = $batch->enrollments()
                ->where('user_id', $student->id)
                ->where('status', 'active')
                ->exists();
            abort_unless($enrolled, 403, 'You are not actively enrolled in this batch.');

            $priorCheckIn = DB::table('attendance_qr_checkins')
                ->where('session_id', $qrSession->id)
                ->where('student_id', $student->id)
                ->first();

            if ($priorCheckIn) {
                return response()->json([
                    'data' => ['status' => 'duplicate', 'attendance_id' => $priorCheckIn->attendance_id],
                ]);
            }

            $existing = Attendance::query()
                ->where('batch_id', $batch->id)
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $qrSession->attendance_date)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => [
                        'status' => $existing->status === 'present' ? 'duplicate' : 'conflict',
                        'attendance_id' => $existing->id,
                        'server_status' => $existing->status,
                    ],
                ], $existing->status === 'present' ? 200 : 409);
            }

            $attendance = Attendance::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'marked_by' => $qrSession->created_by,
                'attendance_date' => $qrSession->attendance_date,
                'status' => 'present',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'location_accuracy_m' => $data['location_accuracy_m'],
                'location_captured_at' => $capturedAt,
                'source' => 'qr',
            ]);

            DB::table('attendance_qr_checkins')->insert([
                'session_id' => $qrSession->id,
                'student_id' => $student->id,
                'attendance_id' => $attendance->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('attendance_audits')->insert([
                'attendance_id' => $attendance->id,
                'actor_id' => $student->id,
                'action' => 'qr_checkin',
                'old_status' => null,
                'new_status' => 'present',
                'reason' => null,
                'metadata' => json_encode(['qr_session_id' => $qrSession->public_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'data' => ['status' => 'synced', 'attendance_id' => $attendance->id],
            ], 201);
        });
    }

    private function rememberResult(int $actorId, array $record, string $payloadHash, array $result): array
    {
        DB::table('attendance_sync_operations')->insert([
            'actor_id' => $actorId,
            'idempotency_key' => $record['idempotency_key'],
            'payload_hash' => $payloadHash,
            'result_status' => $result['status'],
            'result_payload' => json_encode($result, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }
}
