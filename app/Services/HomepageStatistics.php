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
            $hasBranches = Schema::hasTable('branches');
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

            $activeEnrollments = $hasEnrollments && $hasUsers
                ? DB::table('enrollments')
                    ->join('users', 'users.id', '=', 'enrollments.user_id')
                    ->where('users.role', 'student')
                    ->where('enrollments.status', 'active')
                    ->count('enrollments.id')
                : 0;
            $uniqueEnrolledStudents = $hasEnrollments && $hasUsers
                ? DB::table('enrollments')
                    ->join('users', 'users.id', '=', 'enrollments.user_id')
                    ->where('users.role', 'student')
                    ->where('enrollments.status', 'active')
                    ->distinct()
                    ->count('enrollments.user_id')
                : 0;
            $completedEnrollments = $hasEnrollments && $hasUsers
                ? DB::table('enrollments')
                    ->join('users', 'users.id', '=', 'enrollments.user_id')
                    ->where('users.role', 'student')
                    ->where(function ($query) {
                        $query->where('enrollments.status', 'completed')
                            ->orWhereNotNull('enrollments.completed_at');
                    })
                    ->count('enrollments.id')
                : 0;
            $openBatches = $hasBatches && $hasBranches && $hasCourses
                ? DB::table('batches')
                    ->join('branches', 'branches.id', '=', 'batches.branch_id')
                    ->join('courses', 'courses.id', '=', 'batches.course_id')
                    ->where('batches.status', 'open')
                    ->where('batches.ends_at', '>=', now())
                    ->where('branches.is_active', true)
                    ->where('courses.status', 'published')
                    ->count('batches.id')
                : 0;
            $issuedCertificates = $hasCertificates && $hasUsers
                ? DB::table('certificates')
                    ->join('users', 'users.id', '=', 'certificates.user_id')
                    ->where('users.role', 'student')
                    ->where('certificates.status', 'issued')
                    ->count('certificates.id')
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
