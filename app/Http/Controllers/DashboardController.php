<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
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

        $stats = [
            'courses' => Course::count(),
            'students' => \App\Models\User::where('role', 'student')->count(),
            'teachers' => \App\Models\User::where('role', 'teacher')->count(),
            'certificates' => Certificate::count(),
        ];

        return view('dashboard', compact('user', 'courses', 'enrolledCourses', 'recentCertificates', 'stats', 'announcementSlides'));
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
