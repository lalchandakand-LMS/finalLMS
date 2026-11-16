<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceSyncController extends Controller
{
    /**
     * Accept a bounded set of offline attendance operations.
     * The server remains authoritative; conflicting records are never overwritten.
     */
    public function sync(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isTeacher(), 403, 'Only authenticated teachers can sync attendance.');

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

        $actor = $request->user();
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

                $enrolled = $batch->enrollments()
                    ->where('user_id', $record['student_id'])
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

                $capturedAt = now()->parse($record['location_captured_at']);
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
                    ->where('student_id', $record['student_id'])
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
                    'student_id' => $record['student_id'],
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
