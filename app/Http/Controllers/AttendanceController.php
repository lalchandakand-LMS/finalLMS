<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $auditHistory = DB::table('attendance_audits')
            ->join('attendances', 'attendances.id', '=', 'attendance_audits.attendance_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'attendance_audits.actor_id')
            ->join('users as students', 'students.id', '=', 'attendances.student_id')
            ->where('attendances.batch_id', $batch->id)
            ->whereDate('attendances.attendance_date', request('date', now()->toDateString()))
            ->orderByDesc('attendance_audits.created_at')
            ->limit(100)
            ->get([
                'attendance_audits.*',
                'actors.name as actor_name',
                'students.name as student_name',
                'attendances.attendance_date',
            ]);

        $user = Auth::user();
        $attendanceStoreRoute = $user->isBranchManager()
            ? route('admin.branch-workspace.batches.attendance.store', $batch)
            : ($user->isAdmin()
                ? route('admin.batches.attendance.store', $batch)
                : route('teacher.batches.attendance.store', $batch));
        $attendanceBackUrl = $user->isBranchManager()
            ? route('admin.branch-workspace.batches.index')
            : ($user->isAdmin() ? route('admin.batches.index') : route('teacher.batches.index'));

        return view('teacher.batches.attendance', compact(
            'batch',
            'students',
            'attendance',
            'attendanceStoreRoute',
            'attendanceBackUrl'
        ));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatch($batch);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'location_captured_at' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! config('attendance.allow_excused', false)
            && collect($validated['records'])->contains(fn (array $record): bool => $record['status'] === 'excused')) {
            return back()->withErrors([
                'records' => 'Excused attendance is disabled by institutional policy.',
            ])->withInput();
        }

        $capturedAt = now()->parse($validated['location_captured_at']);
        if ($capturedAt->greaterThan(now()->addMinutes(5))) {
            return back()->withErrors(['location_captured_at' => 'Invalid location capture time.'])->withInput();
        }

        DB::transaction(function () use ($batch, $validated, $capturedAt): void {
            $enrolledStudentIds = $batch->enrollments()
                ->where('status', 'active')
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($validated['records'] as $record) {
                abort_unless(in_array((int) $record['student_id'], $enrolledStudentIds, true), 403);

                $attendance = Attendance::query()
                    ->where('batch_id', $batch->id)
                    ->where('student_id', $record['student_id'])
                    ->whereDate('attendance_date', $validated['attendance_date'])
                    ->lockForUpdate()
                    ->first();

                $oldStatus = $attendance?->status;
                $isCorrection = $attendance !== null && $oldStatus !== $record['status'];
                $reason = trim((string) ($record['reason'] ?? ''));

                if ($isCorrection && $reason === '') {
                    abort(422, 'A correction reason is required when changing an existing attendance record.');
                }

                if (! $attendance) {
                    $attendance = new Attendance([
                        'batch_id' => $batch->id,
                        'student_id' => $record['student_id'],
                        'attendance_date' => $validated['attendance_date'],
                    ]);
                }

                $attendance->fill([
                    'marked_by' => Auth::id(),
                    'status' => $record['status'],
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'location_accuracy_m' => $validated['location_accuracy_m'],
                    'location_captured_at' => $capturedAt,
                    'source' => 'online',
                ]);
                $attendance->save();

                if ($isCorrection || $oldStatus === null) {
                    DB::table('attendance_audits')->insert([
                        'attendance_id' => $attendance->id,
                        'actor_id' => Auth::id(),
                        'action' => $isCorrection ? 'corrected' : 'marked',
                        'old_status' => $oldStatus,
                        'new_status' => $record['status'],
                        'reason' => $isCorrection ? $reason : null,
                        'metadata' => json_encode([
                            'location_accuracy_m' => $validated['location_accuracy_m'],
                            'source' => 'online',
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $route = Auth::user()->isBranchManager()
            ? 'admin.branch-workspace.batches.attendance.index'
            : (Auth::user()->isAdmin() ? 'admin.batches.attendance.index' : 'teacher.batches.attendance.index');

        return redirect()->route($route, ['batch' => $batch, 'date' => $validated['attendance_date']])
            ->with('success', 'Attendance saved successfully.');
    }


    public function report(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isAdmin() || $actor->isTeacher()
            || ($actor->isBranchManager() && $actor->hasAdminPermission('branch.attendance'))), 403);

        $filters = $request->validate([
            'academic_level_id' => ['nullable', 'integer', 'exists:academic_levels,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = Attendance::query()->with([
            'student:id,name,email',
            'batch.course.academicLevel',
            'batch.branch',
        ]);

        if ($actor->isTeacher()) {
            $query->whereHas('batch', fn ($batches) => $batches->where('teacher_id', $actor->id));
        } elseif ($actor->isBranchManager()) {
            abort_unless($actor->branch_id, 403);
            $query->whereHas('batch', fn ($batches) => $batches->where('branch_id', $actor->branch_id));
        }

        if (! empty($filters['academic_level_id'])) {
            $query->whereHas('batch.course', fn ($courses) => $courses->where('academic_level_id', $filters['academic_level_id']));
        }
        if (! empty($filters['branch_id']) && $actor->isAdmin()) {
            $query->whereHas('batch', fn ($batches) => $batches->where('branch_id', $filters['branch_id']));
        }
        if (! empty($filters['course_id'])) {
            $query->whereHas('batch', fn ($batches) => $batches->where('course_id', $filters['course_id']));
        }
        if (! empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('attendance_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('attendance_date', '<=', $filters['date_to']);
        }

        $summary = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $attendanceRecords = $query->latest('attendance_date')->paginate(50)->withQueryString();
        $academicLevels = AcademicLevel::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $branches = $actor->isAdmin()
            ? Branch::query()->orderBy('name')->get(['id', 'name'])
            : Branch::query()->whereKey($actor->branch_id)->get(['id', 'name']);
        $courses = Course::query()
            ->when($actor->isTeacher(), fn ($courses) => $courses->where('teacher_id', $actor->id))
            ->when($actor->isBranchManager(), fn ($courses) => $courses->whereHas('batches', fn ($batches) => $batches->where('branch_id', $actor->branch_id)))
            ->orderBy('title')
            ->get(['id', 'title']);
        $batches = Batch::query()
            ->when($actor->isTeacher(), fn ($batches) => $batches->where('teacher_id', $actor->id))
            ->when($actor->isBranchManager(), fn ($batches) => $batches->where('branch_id', $actor->branch_id))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('attendance.report', compact(
            'filters',
            'summary',
            'attendanceRecords',
            'academicLevels',
            'branches',
            'courses',
            'batches'
        ));
    }

    private function authorizeBatch(Batch $batch): void
    {
        $user = Auth::user();

        $authorized = $user?->isAdmin()
            || ($user?->isTeacher() && (int) $batch->teacher_id === (int) $user->id)
            || ($user?->isBranchManager()
                && (int) $user->branch_id === (int) $batch->branch_id
                && $user->hasAdminPermission('branch.attendance'));

        abort_unless($authorized, 403);
    }
}
