<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return view('teacher.batches.attendance', compact('batch', 'students', 'attendance'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatch($batch);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:present,absent,late'],
        ]);

        $enrolledStudentIds = $batch->enrollments()
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        foreach ($validated['records'] as $record) {
            abort_unless(in_array((int) $record['student_id'], $enrolledStudentIds, true), 403);

            Attendance::updateOrCreate(
                [
                    'batch_id' => $batch->id,
                    'student_id' => $record['student_id'],
                    'attendance_date' => $validated['attendance_date'],
                ],
                [
                    'marked_by' => Auth::id(),
                    'status' => $record['status'],
                ]
            );
        }

        return redirect()->route('teacher.batches.attendance.index', ['batch' => $batch, 'date' => $validated['attendance_date']])
            ->with('success', 'Attendance saved successfully.');
    }

    private function authorizeBatch(Batch $batch): void
    {
        $user = Auth::user();

        abort_unless($user->isAdmin() || ($user->isTeacher() && $batch->teacher_id === $user->id), 403);
    }
}