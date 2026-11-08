<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\AssignmentSubmission;
use App\Models\ExamAttempt;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\DashboardAnnouncement;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $announcementSlides = DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', $user->role])
            ->with('image')
            ->orderByDesc('priority')->latest()->take(8)->get();

        $courses = Course::with('teacher')->where('status', 'published')->latest()->get();
        $enrolledCourses = $user->isTeacher()
            ? Course::with('teacher')->where('teacher_id', $user->id)->latest()->get()
            : Course::whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->with('teacher')->get();

        $recentCertificatesQuery = Certificate::with(['course', 'user'])
            ->latest('issued_at');

        if ($user->isStudent()) {
            $recentCertificatesQuery->where('user_id', $user->id);
        } elseif ($user->isTeacher()) {
            $recentCertificatesQuery->whereHas('course', fn ($query) => $query->where('teacher_id', $user->id));
        }

        $recentCertificates = $recentCertificatesQuery->take(5)->get();

        $studentInsights = null;
        $teacherInsights = null;

        if ($user->isStudent()) {
            $studentEnrollments = Enrollment::where('user_id', $user->id)
                ->whereIn('status', ['active', 'completed'])
                ->get(['course_id']);
            $studentCourseIds = $studentEnrollments->pluck('course_id')->unique()->values();

            $totalLessons = $studentCourseIds->isEmpty()
                ? 0
                : Lesson::whereIn('course_id', $studentCourseIds)->count();
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $studentCourseIds))
                ->count();

            $studentInsights = [
                'enrolled_courses' => $studentEnrollments->count(),
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percent' => $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0,
                'assignments_submitted' => AssignmentSubmission::where('student_id', $user->id)->count(),
                'exams_attempted' => ExamAttempt::where('student_id', $user->id)->count(),
                'attendance_marked' => Attendance::where('student_id', $user->id)->count(),
            ];
        }

        if ($user->isTeacher()) {
            $teacherBatchIds = Batch::where('teacher_id', $user->id)->pluck('id');

            $teacherInsights = [
                'assigned_courses' => Course::where('teacher_id', $user->id)->count(),
                'total_batches' => $teacherBatchIds->count(),
                'active_batches' => Batch::where('teacher_id', $user->id)->where('status', 'open')->count(),
                'assignments_created' => Assignment::where('teacher_id', $user->id)->count(),
                'pending_reviews' => AssignmentSubmission::where('status', 'submitted')
                    ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $user->id))
                    ->count(),
                'graded_submissions' => AssignmentSubmission::where('status', 'graded')
                    ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $user->id))
                    ->count(),
                'active_students' => $teacherBatchIds->isEmpty()
                    ? 0
                    : Enrollment::whereIn('batch_id', $teacherBatchIds)
                        ->where('status', 'active')
                        ->distinct('user_id')
                        ->count('user_id'),
            ];
        }

        $stats = [
            'courses' => Course::count(),
            'students' => \App\Models\User::where('role', 'student')->count(),
            'teachers' => \App\Models\User::where('role', 'teacher')->count(),
            'certificates' => Certificate::count(),
        ];

        return view('dashboard', compact('user', 'courses', 'enrolledCourses', 'recentCertificates', 'stats', 'announcementSlides', 'studentInsights', 'teacherInsights'));
    }

    public function certificate(Certificate $certificate): View
    {
        $user = Auth::user();
        $canView = $user->id === $certificate->user_id
            || $user->isAdmin()
            || ($user->isTeacher() && $certificate->course()->where('teacher_id', $user->id)->exists());

        abort_unless($canView, 403);

        return view('certificates.show', compact('certificate'));
    }
}
