<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $courses = Course::with('teacher')->latest()->get();
        $enrolledCourses = Course::whereHas('enrollments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('teacher')->get();

        $certificatesQuery = Certificate::with(['course', 'user']);
        if ($user->isStudent()) {
            $certificatesQuery->where('user_id', $user->id);
        } elseif ($user->isTeacher()) {
            $certificatesQuery->whereHas('course', fn ($query) => $query->where('teacher_id', $user->id));
        }
        $recentCertificates = $certificatesQuery->latest('issued_at')->take(5)->get();

        $stats = [
            'courses' => Course::count(),
            'students' => \App\Models\User::where('role', 'student')->count(),
            'teachers' => \App\Models\User::where('role', 'teacher')->count(),
            'certificates' => Certificate::count(),
        ];

        return view('dashboard', compact('user', 'courses', 'enrolledCourses', 'recentCertificates', 'stats'));
    }

    public function certificate(Certificate $certificate): View
    {
        $user = Auth::user();
        abort_unless(
            $user->id === $certificate->user_id ||
            $user->isAdmin() ||
            ($user->isTeacher() && $certificate->course()->where('teacher_id', $user->id)->exists()),
            403
        );

        return view('certificates.show', compact('certificate'));
    }
}
