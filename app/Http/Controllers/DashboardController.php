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
use App\Models\Setting;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $institutionName = Setting::value('institution_name', config('app.name'));
        $setupWizardPending = $user->isAdmin() && ! Setting::value('setup_wizard_completed_at');
        $dashboardAppearance = [
            'primary' => Setting::value('dashboard_primary_color', '#102b63'),
            'secondary' => Setting::value('dashboard_secondary_color', '#0759c8'),
            'accent' => Setting::value('dashboard_accent_color', '#ffd52f'),
            'background' => Setting::value('dashboard_background_color', '#f3f6ff'),
            'surface' => Setting::value('dashboard_surface_color', '#ffffff'),
            'hero_title' => Setting::value('dashboard_hero_title', ''),
            'hero_subtitle' => Setting::value('dashboard_hero_subtitle', ''),
            'hero_quote' => Setting::value('dashboard_hero_quote', ''),
            'hero_image' => null,
            'live_class_image' => null,
        ];
        $heroImage = Setting::value('dashboard_hero_image_media_id')
            ? MediaAsset::query()->whereKey((int) Setting::value('dashboard_hero_image_media_id'))->where('mime_type', 'like', 'image/%')->first()
            : null;
        $liveClassImage = Setting::value('dashboard_live_class_image_media_id')
            ? MediaAsset::query()->whereKey((int) Setting::value('dashboard_live_class_image_media_id'))->where('mime_type', 'like', 'image/%')->first()
            : null;
        $dashboardAppearance['hero_image'] = $heroImage?->url;
        $dashboardAppearance['live_class_image'] = $liveClassImage?->url;

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

        return view('dashboard', compact('user', 'courses', 'enrolledCourses', 'recentCertificates', 'stats', 'announcementSlides', 'studentInsights', 'teacherInsights', 'institutionName', 'setupWizardPending', 'dashboardAppearance'));
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
