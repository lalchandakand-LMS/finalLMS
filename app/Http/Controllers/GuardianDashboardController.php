<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\GuardianStudentLink;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
        ]);

        $links = GuardianStudentLink::query()
            ->with('student:id,name,email,branch_id')
            ->where('guardian_id', auth()->id())
            ->where('status', 'approved')
            ->whereNotNull('verified_at')
            ->whereHas('student', fn ($query) => $query->where('role', 'student'))
            ->orderBy('student_id')
            ->get();

        $selectedLink = null;
        if (isset($validated['student_id'])) {
            $selectedLink = $links->firstWhere('student_id', (int) $validated['student_id']);
            abort_unless($selectedLink, 403, 'This student is not linked to your guardian account.');
        } else {
            $selectedLink = $links->first();
        }

        if (! $selectedLink) {
            return view('guardian.dashboard', [
                'links' => $links,
                'selectedLink' => null,
                'student' => null,
                'enrollments' => collect(),
                'attendance' => collect(),
                'attendanceSummary' => collect(),
                'invoices' => collect(),
                'examAttempts' => collect(),
                'certificates' => collect(),
                'totalLessons' => 0,
                'completedLessons' => 0,
            ]);
        }

        $student = $selectedLink->student;
        $enrollments = Enrollment::query()
            ->with(['course.teacher', 'batch'])
            ->where('user_id', $student->id)
            ->latest()
            ->get();

        $courseIds = $enrollments->pluck('course_id')->unique()->values();
        $totalLessons = $courseIds->isEmpty()
            ? 0
            : Lesson::query()->whereIn('course_id', $courseIds)->count();
        $completedLessons = $courseIds->isEmpty()
            ? 0
            : LessonProgress::query()
                ->where('user_id', $student->id)
                ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $courseIds))
                ->count();

        $attendance = Attendance::query()
            ->with('batch.course')
            ->where('student_id', $student->id)
            ->latest('attendance_date')
            ->limit(15)
            ->get();

        $attendanceSummary = Attendance::query()
            ->where('student_id', $student->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoices = Invoice::query()
            ->with(['course', 'payments'])
            ->where('user_id', $student->id)
            ->latest()
            ->limit(10)
            ->get();

        $examAttempts = ExamAttempt::query()
            ->with(['exam.batch'])
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        $certificates = Certificate::query()
            ->with('course')
            ->where('user_id', $student->id)
            ->latest('issued_at')
            ->limit(10)
            ->get();

        return view('guardian.dashboard', compact(
            'links',
            'selectedLink',
            'student',
            'enrollments',
            'attendance',
            'attendanceSummary',
            'invoices',
            'examAttempts',
            'certificates',
            'totalLessons',
            'completedLessons'
        ));
    }
}
