<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\Enrollment;
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
            $studentCourseIds = Enrollment::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'completed'])
                ->pluck('course_id')
                ->filter()
                ->unique()
                ->values();

            $totalLessons = $studentCourseIds->isEmpty()
                ? 0
                : Lesson::query()->whereIn('course_id', $studentCourseIds)->count();

            $completedLessons = $studentCourseIds->isEmpty()
                ? 0
                : LessonProgress::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $studentCourseIds))
                    ->count();

            $studentInsights = [
                'enrolled_courses' => $studentCourseIds->count(),
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percent' => $totalLessons > 0
                    ? min(100, (int) round(($completedLessons / $totalLessons) * 100))
                    : 0,
                'assignments_submitted' => AssignmentSubmission::query()
                    ->where('student_id', $user->id)
                    ->whereIn('status', ['submitted', 'graded'])
                    ->count(),
                'exams_attempted' => ExamAttempt::query()->where('student_id', $user->id)->count(),
                'attendance_marked' => Attendance::query()->where('student_id', $user->id)->count(),
            ];
        }

        if ($user->isTeacher()) {
            $teacherBatchIds = Batch::query()->where('teacher_id', $user->id)->pluck('id');

            $teacherInsights = [
                'assigned_courses' => Course::query()->where('teacher_id', $user->id)->count(),
                'total_batches' => $teacherBatchIds->count(),
                'active_batches' => Batch::query()
                    ->where('teacher_id', $user->id)
                    ->where('status', 'open')
                    ->count(),
                'assignments_created' => Assignment::query()->where('teacher_id', $user->id)->count(),
                'pending_reviews' => AssignmentSubmission::query()
                    ->where('status', 'submitted')
                    ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $user->id))
                    ->count(),
                'graded_submissions' => AssignmentSubmission::query()
                    ->where('status', 'graded')
                    ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $user->id))
                    ->count(),
                'active_students' => $teacherBatchIds->isEmpty()
                    ? 0
                    : Enrollment::query()
                        ->whereIn('batch_id', $teacherBatchIds)
                        ->where('status', 'active')
                        ->distinct()
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
