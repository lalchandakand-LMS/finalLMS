<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseEngagementAnalyticsController extends Controller
{
    public function index(): View
    {
        $courses = Course::query()
            ->with('teacher:id,name')
            ->withCount([
                'enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'active'),
                'enrollments as completed_enrollments_count' => fn ($query) => $query->where('status', 'completed'),
                'curriculumItems as lesson_count',
            ])
            ->orderByDesc('created_at')
            ->paginate(25);

        $courseIds = $courses->getCollection()->modelKeys();
        // Combine the newer course-content player and the legacy lesson player so
        // analytics remain accurate for courses using either curriculum system.
        $activityRows = DB::table('course_content_progress as progress')
            ->join('course_curriculum_items as items', 'items.id', '=', 'progress.course_curriculum_item_id')
            ->selectRaw('items.course_id as course_id, progress.user_id as user_id, COALESCE(progress.progress_percent, 0) as progress_percent, progress.completed_at as completed_at, progress.last_viewed_at as last_viewed_at')
            ->unionAll(
                DB::table('lesson_progress as legacy_progress')
                    ->join('lessons as legacy_lessons', 'legacy_lessons.id', '=', 'legacy_progress.lesson_id')
                    ->selectRaw('legacy_lessons.course_id as course_id, legacy_progress.user_id as user_id, CASE WHEN legacy_progress.completed_at IS NOT NULL THEN 100 ELSE 0 END as progress_percent, legacy_progress.completed_at as completed_at, legacy_progress.last_viewed_at as last_viewed_at')
            );

        $progress = DB::query()->fromSub(clone $activityRows, 'activity')
            ->whereIn('activity.course_id', $courseIds)
            ->select('activity.course_id')
            ->selectRaw('COUNT(*) as progress_records')
            ->selectRaw('COUNT(activity.completed_at) as completed_lessons')
            ->selectRaw('COUNT(DISTINCT activity.user_id) as engaged_students')
            ->selectRaw('AVG(activity.progress_percent) as average_progress')
            ->selectRaw('MAX(activity.last_viewed_at) as last_activity')
            ->groupBy('activity.course_id')
            ->get()
            ->keyBy('course_id');

        $lastActivity = DB::query()->fromSub(clone $activityRows, 'activity')
            ->select('activity.course_id', 'activity.user_id')
            ->selectRaw('MAX(activity.last_viewed_at) as last_activity')
            ->groupBy('activity.course_id', 'activity.user_id');

        $dropouts = DB::table('enrollments as enrollment')
            ->leftJoinSub($lastActivity, 'activity', function ($join): void {
                $join->on('activity.course_id', '=', 'enrollment.course_id')
                    ->on('activity.user_id', '=', 'enrollment.user_id');
            })
            ->whereIn('enrollment.course_id', $courseIds)
            ->where('enrollment.status', 'active')
            ->where(function ($query): void {
                $query->where('activity.last_activity', '<', now()->subDays(30))
                    ->orWhere(function ($neverStarted): void {
                        $neverStarted->whereNull('activity.last_activity')
                            ->where('enrollment.created_at', '<', now()->subDays(30));
                    });
            })
            ->select('enrollment.course_id')
            ->selectRaw('COUNT(*) as dropout_risk_count')
            ->groupBy('enrollment.course_id')
            ->get()
            ->keyBy('course_id');

        $courses->getCollection()->transform(function (Course $course) use ($progress, $dropouts): Course {
            $metrics = $progress->get($course->id);
            $totalEnrollments = $course->active_enrollments_count + $course->completed_enrollments_count;
            $course->setAttribute('engagement_metrics', [
                'total_enrollments' => $totalEnrollments,
                'engaged_students' => (int) ($metrics->engaged_students ?? 0),
                'progress_records' => (int) ($metrics->progress_records ?? 0),
                'completed_lessons' => (int) ($metrics->completed_lessons ?? 0),
                'average_progress' => round((float) ($metrics->average_progress ?? 0), 1),
                'completion_rate' => $totalEnrollments > 0
                    ? round(($course->completed_enrollments_count / $totalEnrollments) * 100, 1) : 0,
                'dropout_risk_count' => (int) ($dropouts->get($course->id)->dropout_risk_count ?? 0),
                'last_activity' => $metrics->last_activity ?? null,
            ]);
            return $course;
        });

        return view('admin.course-analytics.index', compact('courses'));
    }
}
