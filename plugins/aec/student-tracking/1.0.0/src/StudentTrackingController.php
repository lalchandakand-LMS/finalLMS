<?php

namespace Aloms\StudentTracking;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CourseContentProgress;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StudentTrackingController extends Controller
{
    public function index(Request $request, PluginResourceAccess $access): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $query = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->select('users.*')
            ->withCount([
                'enrollments as active_enrollments_count' => fn (Builder $enrollments) => $enrollments->where('status', 'active'),
            ]);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $students) use ($search): void {
                $students->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        $branchFilter = $actor->isAdmin() ? $request->integer('branch_id') : 0;
        if ($branchFilter > 0) {
            $query->where('users.branch_id', $branchFilter);
        }

        $batchFilter = $request->integer('batch_id');
        if ($batchFilter > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $batchFilter): void {
                $access->scopeQuery($enrollments, $actor)->where('batch_id', $batchFilter);
            });
        }

        $courseFilter = $request->integer('course_id');
        if ($courseFilter > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $courseFilter): void {
                $access->scopeQuery($enrollments, $actor)->where('course_id', $courseFilter);
            });
        }

        $since = now()->subDays(29)->startOfDay();
        $this->addTrackingAggregates($query, $since, $actor, $access);

        $totalStudents = (clone $query)->count('users.id');
        $studentIds = (clone $query)->select('users.id');
        $accessibleEnrollments = $access->scopeQuery(Enrollment::query(), $actor)
            ->whereIn('user_id', $studentIds);
        $activeEnrollments = (clone $accessibleEnrollments)
            ->where('status', 'active')
            ->count();
        $attendanceToday = Attendance::query()
            ->whereIn('student_id', (clone $query)->select('users.id'))
            ->whereIn('batch_id', (clone $accessibleEnrollments)->whereNotNull('batch_id')->select('batch_id'))
            ->whereDate('attendance_date', today())
            ->count();

        $students = $query->orderBy('users.name')->paginate(25)->withQueryString();
        $students->getCollection()->transform(function (User $student): User {
            $student->setAttribute('attendance_rate', (int) $student->attendance_total > 0
                ? round(((int) $student->attendance_attended / (int) $student->attendance_total) * 100, 1)
                : null);

            $activityDates = collect([
                $student->last_lesson_activity,
                $student->last_content_activity,
            ])->filter()->sortDesc();

            $student->setAttribute('tracking_last_activity_at', $activityDates->first());
            $lastActivity = $activityDates->first();
            $lowAttendance = (int) $student->attendance_total >= 5 && (float) $student->attendance_rate < 75;
            $inactive = ! $lastActivity || now()->diffInDays($lastActivity) >= 14;
            $student->setAttribute('tracking_needs_attention', $lowAttendance || $inactive);

            return $student;
        });

        $needsAttention = $students->getCollection()->where('tracking_needs_attention', true)->count();
        $branches = $actor->isAdmin()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('student-tracking::index', [
            'students' => $students,
            'summary' => [
                'students' => $totalStudents,
                'active_enrollments' => $activeEnrollments,
                'attendance_today' => $attendanceToday,
                'needs_attention' => $needsAttention,
            ],
            'branches' => $branches,
            'filters' => [
                'q' => $search,
                'branch_id' => $branchFilter ?: null,
                'batch_id' => $batchFilter ?: null,
                'course_id' => $courseFilter ?: null,
            ],
            'portalTitle' => $this->portalTitle($actor),
        ]);
    }

    public function show(Request $request, int $student, PluginResourceAccess $access): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $studentRecord = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->whereKey($student)
            ->firstOrFail();

        $enrollments = $access->scopeQuery(Enrollment::query(), $actor)
            ->where('user_id', $studentRecord->id)
            ->with(['course:id,title,slug', 'batch:id,title,branch_id,teacher_id', 'batch.branch:id,name'])
            ->latest()
            ->get();

        $allowedCourseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
        $allowedBatchIds = $enrollments->pluck('batch_id')->filter()->unique()->values();

        $attendanceRecords = Attendance::query()
            ->where('student_id', $studentRecord->id)
            ->whereIn('batch_id', $allowedBatchIds)
            ->with(['batch:id,title,course_id,branch_id', 'batch.course:id,title', 'batch.branch:id,name'])
            ->latest('attendance_date')
            ->limit(30)
            ->get();

        $lessonProgress = Schema::hasTable('lesson_progress')
            ? LessonProgress::query()
                ->where('user_id', $studentRecord->id)
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $allowedCourseIds))
                ->with(['lesson:id,title,course_id', 'lesson.course:id,title'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
            : collect();

        $contentProgress = Schema::hasTable('course_content_progress')
            ? CourseContentProgress::query()
                ->where('user_id', $studentRecord->id)
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $allowedCourseIds))
                ->with(['curriculumItem:id,course_id,course_content_post_id,section_title', 'curriculumItem.course:id,title', 'curriculumItem.post:id,title'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
            : collect();

        $attendanceTotal = $attendanceRecords->count();
        $attendedTotal = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $attendanceTotal > 0 ? round(($attendedTotal / $attendanceTotal) * 100, 1) : null;

        return view('student-tracking::show', [
            'student' => $studentRecord,
            'enrollments' => $enrollments,
            'attendanceRecords' => $attendanceRecords,
            'lessonProgress' => $lessonProgress,
            'contentProgress' => $contentProgress,
            'attendanceRate' => $attendanceRate,
            'portalTitle' => $this->portalTitle($actor),
        ]);
    }

    private function addTrackingAggregates(
        Builder $query,
        $since,
        User $actor,
        PluginResourceAccess $access
    ): void {
        $scopedBatchIds = fn () => $access->scopeQuery(
            Enrollment::query()
                ->select('batch_id')
                ->whereColumn('user_id', 'users.id')
                ->whereNotNull('batch_id'),
            $actor
        );

        $scopedCourseIds = fn () => $access->scopeQuery(
            Enrollment::query()
                ->select('course_id')
                ->whereColumn('user_id', 'users.id'),
            $actor
        );

        $aggregates = [
            'attendance_total' => Attendance::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('attendances.student_id', 'users.id')
                ->whereIn('batch_id', $scopedBatchIds())
                ->whereDate('attendance_date', '>=', $since->toDateString()),
            'attendance_attended' => Attendance::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('attendances.student_id', 'users.id')
                ->whereIn('batch_id', $scopedBatchIds())
                ->whereDate('attendance_date', '>=', $since->toDateString())
                ->whereIn('status', ['present', 'late']),
            'completed_lessons' => DB::raw('0'),
            'completed_content' => DB::raw('0'),
            'last_lesson_activity' => DB::raw('NULL'),
            'last_content_activity' => DB::raw('NULL'),
        ];

        if (Schema::hasTable('lesson_progress')) {
            $aggregates['completed_lessons'] = LessonProgress::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('lesson_progress.user_id', 'users.id')
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $scopedCourseIds()))
                ->whereNotNull('completed_at');
            $aggregates['last_lesson_activity'] = LessonProgress::query()
                ->selectRaw('MAX(COALESCE(last_viewed_at, completed_at, updated_at))')
                ->whereColumn('lesson_progress.user_id', 'users.id')
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $scopedCourseIds()));
        }

        if (Schema::hasTable('course_content_progress')) {
            $aggregates['completed_content'] = CourseContentProgress::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('course_content_progress.user_id', 'users.id')
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $scopedCourseIds()))
                ->whereNotNull('completed_at');
            $aggregates['last_content_activity'] = CourseContentProgress::query()
                ->selectRaw('MAX(COALESCE(last_viewed_at, completed_at, updated_at))')
                ->whereColumn('course_content_progress.user_id', 'users.id')
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $scopedCourseIds()));
        }

        $query->addSelect($aggregates);
    }

    private function portalTitle(User $actor): string
    {
        return match ($actor->role) {
            'admin' => 'Student Tracking',
            'branch_manager' => 'Branch Student Tracking',
            'teacher' => 'My Students',
            'guardian' => 'Children Learning Tracking',
            default => 'My Learning Tracking',
        };
    }
}
