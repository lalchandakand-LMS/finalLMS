<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSyncReview;
use App\Models\Batch;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InstructorAttendanceSyncController extends Controller
{
    public function policy(Request $request): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher instanceof User && $teacher->isTeacher(), 403);

        return response()->json([
            'allow_excused' => filter_var(
                Setting::value('attendance_allow_excused', '0'),
                FILTER_VALIDATE_BOOLEAN
            ),
            'max_location_age_minutes' => max(1, min(1440, (int) Setting::value('attendance_max_location_age_minutes', '10'))),
            'stale_location_action' => Setting::value('attendance_stale_location_action', 'reject') === 'manual_review' ? 'manual_review' : 'reject',
        ]);
    }

    /**
     * Accept a bounded batch of offline attendance operations.
     *
     * Existing attendance is never overwritten by a sync retry. A differing
     * record is returned as a conflict for explicit, audited resolution.
     */
    public function sync(Request $request): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher instanceof User && $teacher->isTeacher(), 403);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:50'],
        ]);

        $allowedStatuses = ['present', 'absent', 'late'];
        if (filter_var(Setting::value('attendance_allow_excused', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $allowedStatuses[] = 'excused';
        }

        $results = [];
        foreach ($validated['records'] as $record) {
            $key = is_array($record) ? ($record['idempotency_key'] ?? null) : null;
            $validation = Validator::make(is_array($record) ? $record : [], [
                'idempotency_key' => ['required', 'uuid'],
                'batch_id' => ['required', 'integer', 'min:1'],
                'student_id' => ['required', 'integer', 'min:1'],
                'attendance_date' => ['required', 'date_format:Y-m-d'],
                'status' => ['required', 'in:'.implode(',', $allowedStatuses)],
                'location.latitude' => ['required', 'numeric', 'between:-90,90'],
                'location.longitude' => ['required', 'numeric', 'between:-180,180'],
                'location.accuracy_meters' => ['required', 'numeric', 'min:0', 'max:1000'],
                'location.captured_at' => ['required', 'date'],
            ]);

            if ($validation->fails()) {
                $results[] = [
                    'idempotency_key' => $key,
                    'status' => 'failed',
                    'message' => 'Invalid attendance or foreground location data.',
                    'errors' => $validation->errors(),
                ];
                continue;
            }

            $data = $validation->validated();
            try {
                $results[] = $this->syncOne($teacher, $data);
            } catch (QueryException $exception) {
                // A concurrent retry may win the unique idempotency-key race.
                $existing = Attendance::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($existing && (int) $existing->batch_id === (int) $data['batch_id']
                    && (int) $existing->student_id === (int) $data['student_id']
                    && $existing->attendance_date?->toDateString() === $data['attendance_date']
                    && $existing->status === $data['status']) {
                    $results[] = [
                        'idempotency_key' => $data['idempotency_key'],
                        'status' => 'duplicate',
                    ];
                    continue;
                }

                report($exception);
                $results[] = [
                    'idempotency_key' => $data['idempotency_key'],
                    'status' => 'failed',
                    'message' => 'The attendance operation could not be saved. Retry later.',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function syncOne(User $teacher, array $data): array
    {
        return DB::transaction(function () use ($teacher, $data): array {
            $key = $data['idempotency_key'];
            $existingReview = AttendanceSyncReview::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($existingReview) {
                if ($existingReview->review_status === 'approved') {
                    return [
                        'idempotency_key' => $key,
                        'status' => 'duplicate',
                    ];
                }

                return [
                    'idempotency_key' => $key,
                    'status' => $existingReview->review_status === 'pending' ? 'needs_review' : 'failed',
                    'review_id' => $existingReview->id,
                    'message' => $existingReview->review_status === 'pending'
                        ? 'This attendance record is waiting for administrator review.'
                        : 'This attendance review was rejected.',
                ];
            }

            $existingKey = Attendance::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existingKey) {
                return [
                    'idempotency_key' => $key,
                    'status' => ((int) $existingKey->batch_id === (int) $data['batch_id']
                        && (int) $existingKey->student_id === (int) $data['student_id']
                        && $existingKey->attendance_date?->toDateString() === $data['attendance_date']
                        && $existingKey->status === $data['status'])
                        ? 'duplicate' : 'failed',
                    'message' => 'This idempotency key has already been used.',
                ];
            }

            $batch = Batch::query()
                ->whereKey($data['batch_id'])
                ->where('teacher_id', $teacher->id)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                return [
                    'idempotency_key' => $key,
                    'status' => 'failed',
                    'message' => 'Batch is not assigned to this instructor.',
                ];
            }

            $activeEnrollment = $batch->enrollments()
                ->where('user_id', $data['student_id'])
                ->where('status', 'active')
                ->exists();

            if (! $activeEnrollment) {
                return [
                    'idempotency_key' => $key,
                    'status' => 'failed',
                    'message' => 'Student does not have an active enrollment in this batch.',
                ];
            }

            $capturedAt = Carbon::parse($data['location']['captured_at']);
            $maxLocationAgeMinutes = max(1, min(1440, (int) Setting::value('attendance_max_location_age_minutes', '10')));
            if ($capturedAt->lt(now()->subMinutes($maxLocationAgeMinutes))) {
                if (Setting::value('attendance_stale_location_action', 'reject') === 'manual_review') {
                    $review = AttendanceSyncReview::query()->firstOrCreate(
                        ['idempotency_key' => $key],
                        [
                            'teacher_id' => $teacher->id,
                            'batch_id' => $batch->id,
                            'student_id' => $data['student_id'],
                            'attendance_date' => $data['attendance_date'],
                            'attendance_status' => $data['status'],
                            'location_latitude' => $data['location']['latitude'],
                            'location_longitude' => $data['location']['longitude'],
                            'location_accuracy_meters' => $data['location']['accuracy_meters'],
                            'location_captured_at' => $capturedAt,
                            'review_status' => 'pending',
                        ]
                    );

                    return [
                        'idempotency_key' => $key,
                        'status' => 'needs_review',
                        'review_id' => $review->id,
                        'message' => 'Location capture is stale and waiting for administrator review.',
                    ];
                }

                return [
                    'idempotency_key' => $key,
                    'status' => 'failed',
                    'message' => 'Location capture exceeds the institution maximum age policy.',
                ];
            }

            if ($capturedAt->isAfter(now()->addMinutes(2))) {
                return [
                    'idempotency_key' => $key,
                    'status' => 'failed',
                    'message' => 'Location capture time is in the future.',
                ];
            }

            $existingAttendance = Attendance::query()
                ->where('batch_id', $batch->id)
                ->where('student_id', $data['student_id'])
                ->whereDate('attendance_date', $data['attendance_date'])
                ->lockForUpdate()
                ->first();

            if ($existingAttendance) {
                return [
                    'idempotency_key' => $key,
                    'attendance_id' => $existingAttendance->id,
                    'status' => $existingAttendance->status === $data['status'] ? 'duplicate' : 'conflict',
                    'message' => $existingAttendance->status === $data['status']
                        ? 'The same attendance is already recorded.'
                        : 'A different attendance record exists. Staff review is required.',
                ];
            }

            $attendance = Attendance::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $data['student_id'],
                'marked_by' => $teacher->id,
                'attendance_date' => $data['attendance_date'],
                'status' => $data['status'],
                'idempotency_key' => $key,
                'location_latitude' => $data['location']['latitude'],
                'location_longitude' => $data['location']['longitude'],
                'location_accuracy_meters' => $data['location']['accuracy_meters'],
                'location_captured_at' => $capturedAt,
            ]);

            DB::table('attendance_change_logs')->insert([
                'attendance_id' => $attendance->id,
                'actor_id' => $teacher->id,
                'previous_status' => null,
                'new_status' => $attendance->status,
                'reason' => null,
                'source' => 'offline_sync',
                'created_at' => now(),
            ]);

            return [
                'idempotency_key' => $key,
                'status' => 'synced',
                'attendance_id' => $attendance->id,
            ];
        }, 3);
    }
    /**
     * Explicitly resolve an existing attendance conflict with an audit reason.
     */
    public function resolveConflict(Request $request, Attendance $attendance): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher instanceof User && $teacher->isTeacher(), 403);
        abort_unless((int) $attendance->batch()->value('teacher_id') === (int) $teacher->id, 404);

        $allowedStatuses = ['present', 'absent', 'late'];
        if (filter_var(Setting::value('attendance_allow_excused', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $allowedStatuses[] = 'excused';
        }
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', $allowedStatuses)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $updated = DB::transaction(function () use ($teacher, $attendance, $validated): Attendance {
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
            $batch = Batch::query()->whereKey($locked->batch_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $batch->teacher_id === (int) $teacher->id, 404);

            $previousStatus = $locked->status;
            $locked->update([
                'status' => $validated['status'],
                'marked_by' => $teacher->id,
            ]);

            DB::table('attendance_change_logs')->insert([
                'attendance_id' => $locked->id,
                'actor_id' => $teacher->id,
                'previous_status' => $previousStatus,
                'new_status' => $validated['status'],
                'reason' => trim($validated['reason']),
                'source' => 'offline_conflict_resolution',
                'created_at' => now(),
            ]);

            return $locked->fresh();
        }, 3);

        return response()->json([
            'message' => 'Attendance conflict resolved and audited.',
            'attendance' => [
                'id' => $updated->id,
                'status' => $updated->status,
            ],
        ]);
    }

}
