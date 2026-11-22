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
        $progress = DB::table('course_content_progress as progress')
            ->join('course_curriculum_items as items', 'items.id', '=', 'progress.course_curriculum_item_id')
            ->whereIn('items.course_id', $courseIds)
            ->select('items.course_id')
            ->selectRaw('COUNT(*) as progress_records')
            ->selectRaw('COUNT(progress.completed_at) as completed_lessons')
            ->selectRaw('COUNT(DISTINCT progress.user_id) as engaged_students')
            ->selectRaw('AVG(progress.progress_percent) as average_progress')
            ->selectRaw('MAX(progress.last_viewed_at) as last_activity')
            ->groupBy('items.course_id')
            ->get()
            ->keyBy('course_id');

        $lastActivity = DB::table('course_content_progress as progress')
            ->join('course_curriculum_items as items', 'items.id', '=', 'progress.course_curriculum_item_id')
            ->select('items.course_id', 'progress.user_id')
            ->selectRaw('MAX(progress.last_viewed_at) as last_activity')
            ->groupBy('items.course_id', 'progress.user_id');

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
