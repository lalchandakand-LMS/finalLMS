<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AttendanceQrController extends Controller
{
    public function createSession(Request $request, Batch $batch): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher instanceof User && $teacher->isTeacher()
            && (int) $batch->teacher_id === (int) $teacher->id
            && ($teacher->branch_id === null || (int) $teacher->branch_id === (int) $batch->branch_id), 403);

        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d'],
        ]);
        abort_unless(Carbon::createFromFormat('Y-m-d', $data['attendance_date'])->isToday(), 422, 'QR attendance sessions can only be created for today.');

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

        return response()->json([
            'data' => [
                'session_id' => $publicId,
                'check_in_url' => URL::temporarySignedRoute(
                    'api.v1.attendance.qr.checkin',
                    $expiresAt,
                    ['session' => $publicId, 'nonce' => $nonce]
                ),
                'expires_at' => $expiresAt->toISOString(),
                'expires_in_seconds' => now()->diffInSeconds($expiresAt),
            ],
        ], 201);
    }

    public function checkIn(Request $request, string $session): JsonResponse
    {
        $student = $request->user();
        abort_unless($student instanceof User && $student->isStudent(), 403);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:1000'],
            'captured_at' => ['required', 'date'],
        ]);

        $qrSession = DB::table('attendance_qr_sessions')
            ->where('public_id', $session)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($qrSession, 410, 'This QR attendance session has expired or was revoked.');

        $nonce = (string) $request->query('nonce', '');
        abort_unless($nonce !== '' && hash_equals($qrSession->nonce_hash, hash('sha256', $nonce)), 403);

        $capturedAt = Carbon::parse($data['captured_at']);
        $sessionCreatedAt = Carbon::parse($qrSession->created_at);
        abort_if(
            $capturedAt->lt($sessionCreatedAt->copy()->subSeconds(15))
                || $capturedAt->lt(now()->subSeconds(120))
                || $capturedAt->gt(now()->addSeconds(30)),
            422,
            'QR check-in requires a fresh foreground location capture taken during the active QR session.'
        );
        $maxAge = max(1, min(1440, (int) Setting::value('attendance_max_location_age_minutes', '10')));
        abort_if($capturedAt->greaterThan(now()->addMinutes(5))
            || $capturedAt->lessThan(now()->subMinutes($maxAge)), 422, 'Invalid or stale foreground location capture.');

        return DB::transaction(function () use ($student, $qrSession, $data, $capturedAt): JsonResponse {
            $lockedSession = DB::table('attendance_qr_sessions')->where('id', $qrSession->id)->lockForUpdate()->first();
            abort_unless($lockedSession && $lockedSession->revoked_at === null
                && Carbon::parse($lockedSession->expires_at)->isFuture(), 410);

            $batch = Batch::query()->findOrFail($lockedSession->batch_id);
            abort_unless((int) $batch->teacher_id === (int) $lockedSession->created_by, 403);
            abort_unless($student->branch_id === null || (int) $student->branch_id === (int) $batch->branch_id, 403);
            abort_unless($batch->enrollments()->where('user_id', $student->id)->where('status', 'active')->exists(), 403);

            $prior = DB::table('attendance_qr_checkins')
                ->where('session_id', $lockedSession->id)
                ->where('student_id', $student->id)
                ->first();
            if ($prior) {
                return response()->json(['data' => ['status' => 'duplicate', 'attendance_id' => $prior->attendance_id]]);
            }

            $existing = Attendance::query()
                ->where('batch_id', $batch->id)
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $lockedSession->attendance_date)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => ['status' => $existing->status === 'present' ? 'duplicate' : 'conflict', 'attendance_id' => $existing->id],
                ], $existing->status === 'present' ? 200 : 409);
            }

            $attendance = Attendance::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'marked_by' => $lockedSession->created_by,
                'attendance_date' => $lockedSession->attendance_date,
                'status' => 'present',
                'location_latitude' => $data['latitude'],
                'location_longitude' => $data['longitude'],
                'location_accuracy_meters' => $data['accuracy_meters'],
                'location_captured_at' => $capturedAt,
            ]);

            DB::table('attendance_qr_checkins')->insert([
                'session_id' => $lockedSession->id,
                'student_id' => $student->id,
                'attendance_id' => $attendance->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('attendance_change_logs')->insert([
                'attendance_id' => $attendance->id,
                'actor_id' => $student->id,
                'previous_status' => null,
                'new_status' => 'present',
                'reason' => null,
                'source' => 'qr',
                'created_at' => now(),
            ]);

            return response()->json(['data' => ['status' => 'synced', 'attendance_id' => $attendance->id]], 201);
        }, 3);
    }
}
