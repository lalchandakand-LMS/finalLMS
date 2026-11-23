<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomepageStatistics
{
    /**
     * Public, aggregate-only metrics. Cache briefly to keep the landing page
     * inexpensive on shared cPanel hosting; no personal data is exposed.
     */
    public function get(): array
    {
        return Cache::remember('homepage.statistics.v1', now()->addMinutes(5), function (): array {
            $hasUsers = Schema::hasTable('users');
            $hasCourses = Schema::hasTable('courses');
            $hasEnrollments = Schema::hasTable('enrollments');
            $hasLessons = Schema::hasTable('lessons');
            $hasBatches = Schema::hasTable('batches');
            $hasCertificates = Schema::hasTable('certificates');

            $students = $hasUsers
                ? DB::table('users')->where('role', 'student')->count()
                : 0;
            $teachers = $hasUsers
                ? DB::table('users')->where('role', 'teacher')->count()
                : 0;
            $publishedCourses = $hasCourses
                ? DB::table('courses')->where('status', 'published')->count()
                : 0;
            $publishedLessons = $hasLessons && $hasCourses
                ? DB::table('lessons')
                    ->join('courses', 'courses.id', '=', 'lessons.course_id')
                    ->where('courses.status', 'published')
                    ->count('lessons.id')
                : 0;

            $activeEnrollments = $hasEnrollments
                ? DB::table('enrollments')->where('status', 'active')->count()
                : 0;
            $uniqueEnrolledStudents = $hasEnrollments && $hasUsers
                ? DB::table('enrollments')
                    ->join('users', 'users.id', '=', 'enrollments.user_id')
                    ->where('users.role', 'student')
                    ->where('enrollments.status', 'active')
                    ->distinct()
                    ->count('enrollments.user_id')
                : 0;
            $completedEnrollments = $hasEnrollments
                ? DB::table('enrollments')
                    ->where(function ($query) {
                        $query->where('status', 'completed')
                            ->orWhereNotNull('completed_at');
                    })
                    ->count()
                : 0;
            $openBatches = $hasBatches
                ? DB::table('batches')->where('status', 'open')->count()
                : 0;
            $issuedCertificates = $hasCertificates
                ? DB::table('certificates')->where('status', 'issued')->count()
                : 0;
            $newStudentsThisMonth = $hasUsers
                ? DB::table('users')->where('role', 'student')
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count()
                : 0;

            return [
                'students' => $students,
                'teachers' => $teachers,
                'courses' => $publishedCourses,
                'lessons' => $publishedLessons,
                'enrollments' => $activeEnrollments,
                'enrolled_students' => $uniqueEnrolledStudents,
                'completed_enrollments' => $completedEnrollments,
                'open_batches' => $openBatches,
                'certificates' => $issuedCertificates,
                'new_students_this_month' => $newStudentsThisMonth,
            ];
        });
    }
}
