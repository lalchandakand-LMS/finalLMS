<?php

namespace App\Http\Controllers;

use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\GuardianStudentLink;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LiveClass;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);

        $links = GuardianStudentLink::query()
            ->where('guardian_user_id', $guardian->id)
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->with(['student.branch'])
            ->latest('verified_at')
            ->get();

        $children = $links->pluck('student')->filter()->values();
        $selectedStudent = null;
        $enrollments = collect();
        $attendance = collect();
        $examAttempts = collect();
        $assignmentSubmissions = collect();
        $payments = collect();
        $invoices = collect();
        $liveClasses = collect();
        $totalLessons = 0;
        $completedLessons = 0;
        $progressPercent = 0;
        $attendanceTotal = 0;
        $attendancePresent = 0;
        $unpaidAmount = 0;

        if ($children->isNotEmpty()) {
            $requestedStudentId = $request->integer('student_id');
            $selectedStudent = $requestedStudentId
                ? $children->firstWhere('id', $requestedStudentId)
                : $children->first();

            abort_unless($selectedStudent !== null, 404);

            $enrollments = Enrollment::query()
                ->where('user_id', $selectedStudent->id)
                ->with(['course.teacher', 'batch.branch'])
                ->latest()
                ->get();

            $attendance = Attendance::query()
                ->where('student_id', $selectedStudent->id)
                ->with(['batch.course'])
                ->latest('attendance_date')
                ->limit(12)
                ->get();

            $attendanceTotal = Attendance::query()
                ->where('student_id', $selectedStudent->id)
                ->count();
            $attendancePresent = Attendance::query()
                ->where('student_id', $selectedStudent->id)
                ->where('status', 'present')
                ->count();

            $examAttempts = ExamAttempt::query()
                ->where('student_id', $selectedStudent->id)
                ->with(['exam.batch.course'])
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->limit(10)
                ->get();

            $assignmentSubmissions = AssignmentSubmission::query()
                ->where('student_id', $selectedStudent->id)
                ->with(['assignment.batch.course'])
                ->latest('submitted_at')
                ->limit(10)
                ->get();

            $payments = Payment::query()
                ->where('user_id', $selectedStudent->id)
                ->with(['course', 'batch'])
                ->latest()
                ->limit(10)
                ->get();

            $invoices = Invoice::query()
                ->where('user_id', $selectedStudent->id)
                ->with(['course', 'batch', 'payments'])
                ->latest()
                ->limit(10)
                ->get();

            $unpaidAmount = (float) Invoice::query()
                ->where('user_id', $selectedStudent->id)
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->sum('amount');

            $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
            if ($courseIds->isNotEmpty()) {
                $totalLessons = Lesson::query()->whereIn('course_id', $courseIds)->count();
                $completedLessons = LessonProgress::query()
                    ->where('user_id', $selectedStudent->id)
                    ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $courseIds))
                    ->count();
                $progressPercent = $totalLessons > 0
                    ? (int) round(($completedLessons / $totalLessons) * 100)
                    : 0;
            }

            $liveClasses = LiveClass::query()
                ->where('status', 'published')
                ->whereHas('batch.enrollments', fn ($query) => $query
                    ->where('user_id', $selectedStudent->id)
                    ->where('status', 'active'))
                ->with(['batch.course'])
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->orderBy('starts_at')
                ->limit(10)
                ->get();
        }

        $pendingRequests = GuardianStudentLink::query()
            ->where('guardian_user_id', $guardian->id)
            ->where('status', 'pending')
            ->with('student:id,name')
            ->latest('requested_at')
            ->get();

        return view('guardian.dashboard', compact(
            'guardian',
            'children',
            'selectedStudent',
            'pendingRequests',
            'enrollments',
            'attendance',
            'attendanceTotal',
            'attendancePresent',
            'examAttempts',
            'assignmentSubmissions',
            'payments',
            'invoices',
            'liveClasses',
            'totalLessons',
            'completedLessons',
            'progressPercent',
            'unpaidAmount'
        ));
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $guardian = $request->user();
        abort_unless($guardian?->isGuardian(), 403);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'relationship' => ['required', 'in:father,mother,legal_guardian,other'],
        ]);

        $student = User::query()
            ->whereKey($validated['student_id'])
            ->where('role', 'student')
            ->first();

        if ($student && $student->id !== $guardian->id) {
            DB::transaction(function () use ($guardian, $student, $validated): void {
                $link = GuardianStudentLink::query()
                    ->where('guardian_user_id', $guardian->id)
                    ->where('student_user_id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if (! $link) {
                    GuardianStudentLink::create([
                        'guardian_user_id' => $guardian->id,
                        'student_user_id' => $student->id,
                        'relationship' => $validated['relationship'],
                        'status' => 'pending',
                        'requested_at' => now(),
                    ]);

                    return;
                }

                if (in_array($link->status, ['rejected', 'revoked'], true)) {
                    $link->update([
                        'relationship' => $validated['relationship'],
                        'status' => 'pending',
                        'requested_at' => now(),
                        'verified_at' => null,
                        'verified_by' => null,
                        'verification_method' => null,
                        'rejected_at' => null,
                        'revoked_at' => null,
                    ]);
                }
            });
        }

        return back()->with('status', __('If the student account exists, a request has been sent for the student to review.'));
    }
}
