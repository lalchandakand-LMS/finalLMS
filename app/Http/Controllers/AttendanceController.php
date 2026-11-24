<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function studentIndex(): View
    {
        $attendanceRecords = Attendance::with(['batch.course', 'batch.branch'])
            ->where('student_id', Auth::id())
            ->latest('attendance_date')
            ->paginate(30);

        return view('attendance.student-index', compact('attendanceRecords'));
    }

    public function index(Batch $batch): View
    {
        $this->authorizeBatch($batch);

        $students = $batch->enrollments()
            ->where('status', 'active')
            ->with('user')
            ->get();
        $attendance = Attendance::where('batch_id', $batch->id)
            ->whereDate('attendance_date', request('date', now()->toDateString()))
            ->pluck('status', 'student_id');

        $user = Auth::user();
        $allowExcused = filter_var(Setting::value('attendance_allow_excused', '0'), FILTER_VALIDATE_BOOLEAN);
        $attendanceStoreRoute = match (true) {
            $user->isBranchManager() => 'admin.branch-workspace.batches.attendance.store',
            $user->isAdmin() => 'admin.batches.attendance.store',
            default => 'teacher.batches.attendance.store',
        };
        $attendanceBackRoute = match (true) {
            $user->isBranchManager() => 'admin.branch-workspace.batches.index',
            $user->isAdmin() => 'admin.batches.index',
            default => 'teacher.batches.index',
        };

        return view('teacher.batches.attendance', compact(
            'batch',
            'students',
            'attendance',
            'attendanceStoreRoute',
            'attendanceBackRoute',
            'allowExcused'
        ));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatch($batch);

        $allowedStatuses = ['present', 'absent', 'late'];
        if (filter_var(Setting::value('attendance_allow_excused', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $allowedStatuses[] = 'excused';
        }

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:'.implode(',', $allowedStatuses)],
            'records.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $batch): void {
            $lockedBatch = Batch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->authorizeBatch($lockedBatch);

            // Read and lock the active enrollment set inside the same transaction
            // as attendance writes so a concurrent cancellation cannot use a stale list.
            $enrolledStudentIds = $lockedBatch->enrollments()
                ->where('status', 'active')
                ->lockForUpdate()
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($validated['records'] as $record) {
                abort_unless(in_array((int) $record['student_id'], $enrolledStudentIds, true), 403);

                $attendance = Attendance::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->where('student_id', $record['student_id'])
                    ->whereDate('attendance_date', $validated['attendance_date'])
                    ->lockForUpdate()
                    ->first();

                if ($attendance && $attendance->status !== $record['status']) {
                    Validator::make($record, [
                        'reason' => ['required', 'string', 'min:5', 'max:500'],
                    ])->validate();

                    $previousStatus = $attendance->status;
                    $attendance->update([
                        'marked_by' => Auth::id(),
                        'status' => $record['status'],
                    ]);

                    DB::table('attendance_change_logs')->insert([
                        'attendance_id' => $attendance->id,
                        'actor_id' => Auth::id(),
                        'previous_status' => $previousStatus,
                        'new_status' => $record['status'],
                        'reason' => trim($record['reason']),
                        'source' => 'web',
                        'created_at' => now(),
                    ]);

                    continue;
                }

                if (! $attendance) {
                    $attendance = Attendance::query()->create([
                        'batch_id' => $lockedBatch->id,
                        'student_id' => $record['student_id'],
                        'marked_by' => Auth::id(),
                        'attendance_date' => $validated['attendance_date'],
                        'status' => $record['status'],
                    ]);

                    DB::table('attendance_change_logs')->insert([
                        'attendance_id' => $attendance->id,
                        'actor_id' => Auth::id(),
                        'previous_status' => null,
                        'new_status' => $record['status'],
                        'reason' => null,
                        'source' => 'web',
                        'created_at' => now(),
                    ]);
                }
            }
        });

        $attendanceRoute = Auth::user()->isBranchManager()
            ? 'admin.branch-workspace.batches.attendance.index'
            : (Auth::user()->isAdmin() ? 'admin.batches.attendance.index' : 'teacher.batches.attendance.index');

        return redirect()->route($attendanceRoute, ['batch' => $batch, 'date' => $validated['attendance_date']])
            ->with('success', 'Attendance saved successfully.');
    }

    private function authorizeBatch(Batch $batch): void
    {
        $user = Auth::user();

        abort_unless(
            $user->isAdmin()
            || ($user->isTeacher() && (int) $batch->teacher_id === (int) $user->id)
            || ($user->isBranchManager()
                && $user->hasAdminPermission('branch.attendance')
                && (int) $batch->branch_id === (int) $user->branch_id),
            403
        );
    }
}