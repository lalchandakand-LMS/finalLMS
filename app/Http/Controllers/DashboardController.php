<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\Enrollment;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $institutionName = Setting::value('institution_name', config('app.name'));
        $setupWizardPending = $user->isAdmin() && ! Setting::value('setup_wizard_completed_at');
        $announcementSlides = DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', $user->role])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id))
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
        } elseif ($user->isGuardian()) {
            $linkedStudents = $user->linkedStudents()->where('users.role', 'student');
            if ($user->branch_id === null) {
                $linkedStudents->whereNull('users.branch_id');
            } elseif (Branch::query()->whereKey($user->branch_id)->where('is_active', true)->exists()) {
                $linkedStudents->where('users.branch_id', $user->branch_id);
            } else {
                $recentCertificatesQuery->whereRaw('1 = 0');
            }
            $recentCertificatesQuery->whereIn('user_id', $linkedStudents->select('users.id'));
        } elseif ($user->isBranchManager()) {
            $activeBranchId = $user->branch_id && Branch::query()->whereKey($user->branch_id)->where('is_active', true)->value('id');
            if ($activeBranchId) {
                $recentCertificatesQuery->whereHas('user', function ($student) use ($activeBranchId): void {
                    $student->where('role', 'student')->where(function ($scope) use ($activeBranchId): void {
                        $scope->where('branch_id', $activeBranchId)
                            ->orWhereHas('enrollments', fn ($enrollments) => $enrollments
                                ->whereIn('status', ['active', 'completed'])
                                ->whereHas('batch', fn ($batch) => $batch->where('branch_id', $activeBranchId)));
                    });
                });
            } else {
                $recentCertificatesQuery->whereRaw('1 = 0');
            }
        } elseif (! $user->isAdmin()) {
            // Finance-only staff must not see academic records.
            $recentCertificatesQuery->whereRaw('1 = 0');
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

            $totalLegacyLessons = $studentCourseIds->isEmpty()
                ? 0
                : Lesson::query()->whereIn('course_id', $studentCourseIds)->count();

            $totalVideoLessons = $studentCourseIds->isEmpty()
                ? 0
                : CourseCurriculumItem::query()
                    ->whereIn('course_id', $studentCourseIds)
                    ->whereHas('post', fn ($posts) => $posts->published())
                    ->count();

            $completedLegacyLessons = $studentCourseIds->isEmpty()
                ? 0
                : LessonProgress::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $studentCourseIds))
                    ->count();

            $completedVideoLessons = $studentCourseIds->isEmpty()
                ? 0
                : CourseContentProgress::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->whereHas('curriculumItem', fn ($items) => $items
                        ->whereIn('course_id', $studentCourseIds)
                        ->whereHas('post', fn ($posts) => $posts->published()))
                    ->count();

            $totalLessons = $totalLegacyLessons + $totalVideoLessons;
            $completedLessons = min($totalLessons, $completedLegacyLessons + $completedVideoLessons);

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

        return view('dashboard', compact('user', 'courses', 'enrolledCourses', 'recentCertificates', 'stats', 'announcementSlides', 'studentInsights', 'teacherInsights', 'institutionName', 'setupWizardPending'));
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
