<?php

namespace Aloms\StudentTracking;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\Branch;
use App\Models\CourseContentProgress;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\AssignmentSubmission;
use App\Models\DashboardAnnouncement;
use App\Models\PluginInstallation;
use App\Models\SupportTicket;
use App\Models\Setting;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Modules\ModuleRegistry;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentTrackingController extends Controller
{
    public function index(Request $request, PluginResourceAccess $access, ModuleRegistry $modules): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $courseTrackingEnabled = $modules->isEnabled('course_management');
        $attendanceTrackingEnabled = $courseTrackingEnabled && $modules->isEnabled('batch_management') && $modules->isEnabled('attendance');
        $lessonTrackingEnabled = $courseTrackingEnabled && $modules->isEnabled('lesson_management');
        $contentTrackingEnabled = $courseTrackingEnabled && $modules->isEnabled('course_content_studio');
        $assignmentTrackingEnabled = $courseTrackingEnabled && $modules->isEnabled('batch_management') && $modules->isEnabled('assignment');
        $examTrackingEnabled = $courseTrackingEnabled && $modules->isEnabled('batch_management') && $modules->isEnabled('exam_management');

        $query = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->select('users.*')
            ->withCount([
                'enrollments as active_enrollments_count' => function (Builder $enrollments) use ($access, $actor): void {
                    $access->scopeQuery($enrollments, $actor)->where('status', 'active');
                },
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

        $validatedDates = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $fromDate = $validatedDates['from_date'] ?? now()->subDays(29)->toDateString();
        $toDate = $validatedDates['to_date'] ?? today()->toDateString();

        $statusFilter = trim((string) $request->query('status', ''));
        $statusOptions = $courseTrackingEnabled
            ? $access->scopeQuery(Enrollment::query(), $actor)
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->values()
            : collect();

        if ($courseTrackingEnabled && $statusFilter !== '') {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $statusFilter): void {
                $access->scopeQuery($enrollments, $actor)->where('status', $statusFilter);
            });
        }

        $batchFilter = $request->integer('batch_id');
        if ($courseTrackingEnabled && $batchFilter > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $batchFilter): void {
                $access->scopeQuery($enrollments, $actor)->where('batch_id', $batchFilter);
            });
        }

        $courseFilter = $request->integer('course_id');
        if ($courseTrackingEnabled && $courseFilter > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $courseFilter): void {
                $access->scopeQuery($enrollments, $actor)->where('course_id', $courseFilter);
            });
        }

        $academicLevelFilter = $request->integer('academic_level_id');
        if ($courseTrackingEnabled && $academicLevelFilter > 0 && Schema::hasColumn('courses', 'academic_level_id')) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $academicLevelFilter): void {
                $access->scopeQuery($enrollments, $actor)
                    ->whereHas('course', fn (Builder $courses) => $courses->where('academic_level_id', $academicLevelFilter));
            });
        }

        $this->addTrackingAggregates($query, $actor, $access, $modules, $fromDate, $toDate);

        $totalStudents = (clone $query)->count('users.id');
        $studentIds = (clone $query)->select('users.id');
        $accessibleEnrollments = $courseTrackingEnabled
            ? $access->scopeQuery(Enrollment::query(), $actor)->whereIn('user_id', $studentIds)
            : Enrollment::query()->whereRaw('1 = 0');
        $activeEnrollments = $courseTrackingEnabled
            ? (clone $accessibleEnrollments)->where('status', 'active')->count()
            : 0;
        $attendanceToday = $attendanceTrackingEnabled
            ? Attendance::query()
                ->whereIn('student_id', (clone $query)->select('users.id'))
                ->whereIn('batch_id', (clone $accessibleEnrollments)->whereNotNull('batch_id')->select('batch_id'))
                ->whereDate('attendance_date', today())
                ->count()
            : 0;

        $riskRules = [
            'low_attendance_percent' => max(1, min(100, (int) Setting::value('student_tracking_low_attendance_percent', 75))),
            'minimum_attendance_records' => max(1, min(30, (int) Setting::value('student_tracking_minimum_attendance_records', 5))),
            'inactive_days' => max(1, min(365, (int) Setting::value('student_tracking_inactive_days', 14))),
        ];

        $students = $query->orderBy('users.name')->paginate(25)->withQueryString();
        $students->getCollection()->transform(function (User $student) use ($riskRules, $actor, $access, $modules): User {
            if (! $courseTrackingEnabled) {
                $student->setAttribute('active_enrollments_count', 0);
            }
            $student->setAttribute('attendance_rate', (int) $student->attendance_total > 0
                ? round(((int) $student->attendance_attended / (int) $student->attendance_total) * 100, 1)
                : null);

            $activityDates = collect([
                $student->last_lesson_activity,
                $student->last_content_activity,
            ])->filter()->sortDesc();

            $student->setAttribute('tracking_last_activity_at', $activityDates->first());
            $lastActivity = $activityDates->first();
            $lowAttendance = (int) $student->attendance_total >= $riskRules['minimum_attendance_records']
                && (float) $student->attendance_rate < $riskRules['low_attendance_percent'];
            $learningTrackingEnabled = $lessonTrackingEnabled || $contentTrackingEnabled;
            $inactive = $learningTrackingEnabled
                && (! $lastActivity || now()->diffInDays($lastActivity) >= $riskRules['inactive_days']);
            $riskFlags = [];
            if ($lowAttendance) {
                $riskFlags[] = [
                    'code' => 'low_attendance',
                    'reason' => __('Attendance below :percent% across at least :count records in the selected date range', [
                        'percent' => $riskRules['low_attendance_percent'],
                        'count' => $riskRules['minimum_attendance_records'],
                    ]),
                ];
            }
            if ($inactive) {
                $riskFlags[] = [
                    'code' => 'inactive_learning',
                    'reason' => __('No recorded LMS learning activity in the last :days days', ['days' => $riskRules['inactive_days']]),
                ];
            }
            if ((int) $student->overdue_assignments_count > 0) {
                $riskFlags[] = [
                    'code' => 'overdue_assignments',
                    'reason' => __(':count published assignment(s) are overdue and have no submission', ['count' => (int) $student->overdue_assignments_count]),
                ];
            }
            $riskFlags = array_merge($riskFlags, $this->additionalRiskSignals($student, $actor, $access, $modules));
            $student->setAttribute('tracking_risk_flags', $riskFlags);
            $student->setAttribute('tracking_risk_reasons', array_column($riskFlags, 'reason'));
            $student->setAttribute('tracking_needs_attention', $riskFlags !== []);

            return $student;
        });

        if (Schema::hasTable('student_tracking_risk_actions')) {
            $pageStudentIds = $students->getCollection()->modelKeys();
            if ($pageStudentIds !== []) {
                $latestRiskActions = DB::table('student_tracking_risk_actions')
                    ->whereIn('student_id', $pageStudentIds)
                    ->whereIn('action', ['dismiss', 'reopen'])
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn ($action) => $action->student_id.':'.$action->risk_code)
                    ->map(fn ($actions) => $actions->last());

                $students->getCollection()->transform(function (User $student) use ($latestRiskActions): User {
                    $flags = collect($student->tracking_risk_flags)
                        ->reject(function (array $flag) use ($student, $latestRiskActions): bool {
                            $latest = $latestRiskActions->get($student->id.':'.$flag['code']);
                            return $latest?->action === 'dismiss';
                        })
                        ->values()
                        ->all();
                    $student->setAttribute('tracking_risk_flags', $flags);
                    $student->setAttribute('tracking_risk_reasons', array_column($flags, 'reason'));
                    $student->setAttribute('tracking_needs_attention', $flags !== []);

                    return $student;
                });
            }
        }

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
            'statusOptions' => $statusOptions,
            'academicLevels' => $courseTrackingEnabled && Schema::hasTable('academic_levels')
                ? DB::table('academic_levels')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])
                : collect(),
            'riskRules' => $riskRules,
            'canConfigureRisk' => $actor->isAdmin() && $this->hasGrantedPermission('student-tracking.configure'),
            'filters' => [
                'q' => $search,
                'branch_id' => $branchFilter ?: null,
                'batch_id' => $batchFilter ?: null,
                'course_id' => $courseFilter ?: null,
                'academic_level_id' => $academicLevelFilter ?: null,
                'status' => $statusFilter ?: null,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'portalTitle' => $this->portalTitle($actor),
        ]);
    }

    public function export(Request $request, PluginResourceAccess $access, ModuleRegistry $modules): StreamedResponse
    {
        $courseTrackingEnabled = $modules->isEnabled('course_management');
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validatedDates = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $fromDate = $validatedDates['from_date'] ?? now()->subDays(29)->toDateString();
        $toDate = $validatedDates['to_date'] ?? today()->toDateString();

        $query = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->select('users.*')
            ->withCount([
                'enrollments as active_enrollments_count' => function (Builder $enrollments) use ($access, $actor): void {
                    $access->scopeQuery($enrollments, $actor)->where('status', 'active');
                },
            ]);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $students) use ($search): void {
                $students->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        if ($actor->isAdmin() && $request->integer('branch_id') > 0) {
            $query->where('users.branch_id', $request->integer('branch_id'));
        }

        $batchId = $request->integer('batch_id');
        if ($courseTrackingEnabled && $batchId > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $batchId): void {
                $access->scopeQuery($enrollments, $actor)->where('batch_id', $batchId);
            });
        }

        $courseId = $request->integer('course_id');
        if ($courseTrackingEnabled && $courseId > 0) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $courseId): void {
                $access->scopeQuery($enrollments, $actor)->where('course_id', $courseId);
            });
        }

        $academicLevelId = $request->integer('academic_level_id');
        if ($courseTrackingEnabled && $academicLevelId > 0 && Schema::hasColumn('courses', 'academic_level_id')) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $academicLevelId): void {
                $access->scopeQuery($enrollments, $actor)
                    ->whereHas('course', fn (Builder $courses) => $courses->where('academic_level_id', $academicLevelId));
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($courseTrackingEnabled && $status !== '') {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($access, $actor, $status): void {
                $access->scopeQuery($enrollments, $actor)->where('status', $status);
            });
        }

        $this->addTrackingAggregates($query, $actor, $access, $modules, $fromDate, $toDate);
        $students = $query->orderBy('users.name')->limit(5000)->get();
        $students->each(function (User $student) use ($modules): void {
            $total = (int) $student->attendance_total;
            $attended = (int) $student->attendance_attended;
            $student->setAttribute('attendance_rate', $total > 0 ? round(($attended / $total) * 100, 1) : null);
            if (! $modules->isEnabled('course_management')) {
                $student->setAttribute('active_enrollments_count', 0);
            }
            $activityDates = collect([$student->last_lesson_activity, $student->last_content_activity])
                ->filter()
                ->sortDesc();
            $student->setAttribute('tracking_last_activity_at', $activityDates->first());
        });

        try {
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'student_tracking.exported',
                'description' => 'Student tracking CSV exported; rows='.$students->count().'; scope='.$actor->role.'.',
                'method' => 'GET',
                'route' => $request->route()?->getName() ?? 'student-tracking.export',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Student tracking export could not be audited; export was blocked.', previous: $exception);
        }

        return response()->streamDownload(function () use ($students): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('CSV export stream could not be opened.');
            }

            fputcsv($stream, [
                'student_id',
                'student_name',
                'attendance_rate_percent',
                'attendance_records',
                'attended_records',
                'active_enrollments',
                'completed_lessons',
                'completed_content',
                'last_learning_activity',
            ]);

            foreach ($students as $student) {
                fputcsv($stream, [
                    $student->id,
                    $this->safeCsvCell((string) $student->name),
                    $student->attendance_rate,
                    (int) $student->attendance_total,
                    (int) $student->attendance_attended,
                    (int) $student->active_enrollments_count,
                    (int) $student->completed_lessons,
                    (int) $student->completed_content,
                    $student->tracking_last_activity_at,
                ]);
            }

            fclose($stream);
        }, 'student-tracking-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function storeRiskAction(Request $request, int $student, PluginResourceAccess $access): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof User
                && ($actor->isAdmin() || $actor->isBranchManager() || $actor->isTeacher())
                && $this->hasGrantedPermission('student-tracking.manage-risk'),
            403
        );

        $studentRecord = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->whereKey($student)
            ->firstOrFail();

        $validated = $request->validate([
            'risk_code' => ['required', 'in:low_attendance,inactive_learning,overdue_assignments,consecutive_absences,declining_scores'],
            'action' => ['required', 'in:dismiss,note,reopen'],
            'note' => ['required_if:action,dismiss,note', 'nullable', 'string', 'max:1000'],
        ]);

        abort_unless(Schema::hasTable('student_tracking_risk_actions'), 503);

        DB::transaction(function () use ($actor, $studentRecord, $validated, $request): void {
            DB::table('student_tracking_risk_actions')->insert([
                'student_id' => $studentRecord->id,
                'actor_id' => $actor->id,
                'risk_code' => $validated['risk_code'],
                'action' => $validated['action'],
                'note' => $validated['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'student_tracking.risk_action',
                'description' => 'Student risk action '.$validated['action'].' recorded for '.$validated['risk_code'].'; note content omitted.',
                'method' => 'POST',
                'route' => $request->route()?->getName() ?? 'student-tracking.risk-actions.store',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        });

        return back()->with('success', __('Student tracking risk action saved.'));
    }

    public function show(Request $request, int $student, PluginResourceAccess $access, ModuleRegistry $modules): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $studentRecord = $access->scopeQuery(User::query(), $actor)
            ->where('users.role', 'student')
            ->whereKey($student)
            ->firstOrFail();

        $courseTrackingAvailable = $modules->isEnabled('course_management');
        $attendanceTrackingAvailable = $courseTrackingAvailable && $modules->isEnabled('batch_management') && $modules->isEnabled('attendance');
        $lessonTrackingAvailable = $courseTrackingAvailable && $modules->isEnabled('lesson_management');
        $contentTrackingAvailable = $courseTrackingAvailable && $modules->isEnabled('course_content_studio');
        $examTrackingAvailable = $courseTrackingAvailable && $modules->isEnabled('batch_management') && $modules->isEnabled('exam_management');
        $assignmentTrackingAvailable = $courseTrackingAvailable && $modules->isEnabled('batch_management') && $modules->isEnabled('assignment');

        $enrollments = $courseTrackingAvailable
            ? $access->scopeQuery(Enrollment::query(), $actor)
                ->where('user_id', $studentRecord->id)
                ->with(['course:id,title,slug', 'batch:id,title,branch_id,teacher_id', 'batch.branch:id,name'])
                ->latest()
                ->get()
            : collect();

        $allowedCourseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
        $noticeTrackingAvailable = $modules->isEnabled('notice_board') && Schema::hasTable('dashboard_announcements');
        $announcements = $noticeTrackingAvailable
            ? DashboardAnnouncement::currentlyActive()
                ->whereIn('audience', ['all', 'student'])
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $studentRecord->branch_id))
                ->where(fn (Builder $query) => $query->whereNull('course_id')->orWhereIn('course_id', $allowedCourseIds))
                ->with(['course:id,title', 'reads' => fn ($reads) => $reads->where('user_id', $studentRecord->id)])
                ->orderByDesc('priority')
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(['id', 'title', 'body', 'course_id', 'branch_id', 'starts_at', 'ends_at', 'created_at'])
            : collect();

        $supportTrackingAvailable = $actor->isAdmin() && $modules->isEnabled('support_center') && Schema::hasTable('support_tickets');
        $supportTickets = $supportTrackingAvailable
            ? SupportTicket::query()
                ->where('user_id', $studentRecord->id)
                ->with('branch:id,name')
                ->latest('last_message_at')
                ->limit(20)
                ->get(['id', 'user_id', 'branch_id', 'subject', 'status', 'last_message_at', 'created_at'])
            : collect();
        $allowedBatchIds = $enrollments->pluck('batch_id')->filter()->unique()->values();

        $attendanceRecords = $modules->isEnabled('attendance')
            ? Attendance::query()
                ->where('student_id', $studentRecord->id)
                ->whereIn('batch_id', $allowedBatchIds)
                ->with(['batch:id,title,course_id,branch_id', 'batch.course:id,title', 'batch.branch:id,name'])
                ->latest('attendance_date')
                ->limit(30)
                ->get()
            : collect();

        $lessonProgress = $modules->isEnabled('lesson_management') && Schema::hasTable('lesson_progress')
            ? LessonProgress::query()
                ->where('user_id', $studentRecord->id)
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $allowedCourseIds))
                ->with(['lesson:id,title,course_id', 'lesson.course:id,title'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
            : collect();

        $contentProgress = $modules->isEnabled('course_content_studio') && Schema::hasTable('course_content_progress')
            ? CourseContentProgress::query()
                ->where('user_id', $studentRecord->id)
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $allowedCourseIds))
                ->with(['curriculumItem:id,course_id,course_content_post_id,section_title', 'curriculumItem.course:id,title', 'curriculumItem.post:id,title'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
            : collect();

        $examAttempts = $modules->isEnabled('exam_management') && Schema::hasTable('exam_attempts')
            ? ExamAttempt::query()
                ->select(['id', 'exam_id', 'student_id', 'submitted_at', 'score', 'status'])
                ->where('student_id', $studentRecord->id)
                ->whereHas('exam', fn (Builder $exams) => $exams->whereIn('batch_id', $allowedBatchIds))
                ->with(['exam:id,title,batch_id'])
                ->latest('submitted_at')
                ->limit(20)
                ->get()
            : collect();

        $overdueAssignments = $modules->isEnabled('assignment') && Schema::hasTable('assignments') && Schema::hasTable('assignment_submissions')
            ? Assignment::query()
                ->whereIn('batch_id', $allowedBatchIds)
                ->where('status', 'published')
                ->where('due_at', '<', now())
                ->whereDoesntHave('submissions', fn (Builder $submissions) => $submissions->where('student_id', $studentRecord->id))
                ->with(['batch:id,title'])
                ->orderBy('due_at')
                ->limit(50)
                ->get(['id', 'batch_id', 'title', 'due_at'])
            : collect();

        $assignmentSubmissions = $modules->isEnabled('assignment') && Schema::hasTable('assignment_submissions')
            ? AssignmentSubmission::query()
                ->select(['id', 'assignment_id', 'student_id', 'submitted_at', 'score', 'status'])
                ->where('student_id', $studentRecord->id)
                ->whereHas('assignment', fn (Builder $assignments) => $assignments->whereIn('batch_id', $allowedBatchIds))
                ->with(['assignment:id,title,batch_id,due_at'])
                ->latest('submitted_at')
                ->limit(20)
                ->get()
            : collect();

        $attendanceTotal = $attendanceRecords->count();
        $attendedTotal = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $attendanceTotal > 0 ? round(($attendedTotal / $attendanceTotal) * 100, 1) : null;
        $riskRules = [
            'low_attendance_percent' => max(1, min(100, (int) Setting::value('student_tracking_low_attendance_percent', 75))),
            'minimum_attendance_records' => max(1, min(30, (int) Setting::value('student_tracking_minimum_attendance_records', 5))),
            'inactive_days' => max(1, min(365, (int) Setting::value('student_tracking_inactive_days', 14))),
        ];
        $activityDates = $lessonProgress->map(fn ($progress) => $progress->last_viewed_at ?? $progress->completed_at ?? $progress->updated_at)
            ->merge($contentProgress->map(fn ($progress) => $progress->last_viewed_at ?? $progress->completed_at ?? $progress->updated_at))
            ->filter()
            ->sortDesc();
        $lastActivity = $activityDates->first();
        $riskFlags = [];
        if ($modules->isEnabled('attendance') && $attendanceTotal >= $riskRules['minimum_attendance_records']
            && $attendanceRate !== null
            && $attendanceRate < $riskRules['low_attendance_percent']) {
            $riskFlags[] = [
                'code' => 'low_attendance',
                'reason' => __('Attendance below :percent% across at least :count records in the selected date range', [
                    'percent' => $riskRules['low_attendance_percent'],
                    'count' => $riskRules['minimum_attendance_records'],
                ]),
            ];
        }
        $learningTrackingEnabled = $modules->isEnabled('lesson_management') || $modules->isEnabled('course_content_studio');
        if ($learningTrackingEnabled && (! $lastActivity || now()->diffInDays($lastActivity) >= $riskRules['inactive_days'])) {
            $riskFlags[] = [
                'code' => 'inactive_learning',
                'reason' => __('No recorded LMS learning activity in the last :days days', ['days' => $riskRules['inactive_days']]),
            ];
        }
        if ($overdueAssignments->isNotEmpty()) {
            $riskFlags[] = [
                'code' => 'overdue_assignments',
                'reason' => __(':count published assignment(s) are overdue and have no submission', ['count' => $overdueAssignments->count()]),
            ];
        }

        $riskFlags = array_merge($riskFlags, $this->additionalRiskSignals($studentRecord, $actor, $access, $modules));
        $canManageRisk = ($actor->isAdmin() || $actor->isBranchManager() || $actor->isTeacher())
            && $this->hasGrantedPermission('student-tracking.manage-risk');

        $riskActions = collect();
        $dismissedRiskFlags = [];
        if (Schema::hasTable('student_tracking_risk_actions')) {
            $latestActions = DB::table('student_tracking_risk_actions')
                ->where('student_id', $studentRecord->id)
                ->whereIn('action', ['dismiss', 'reopen'])
                ->orderBy('id')
                ->get()
                ->groupBy('risk_code')
                ->map(fn ($actions) => $actions->last());
            $dismissedRiskFlags = collect($riskFlags)
                ->filter(fn (array $flag) => $latestActions->get($flag['code'])?->action === 'dismiss')
                ->values()
                ->all();
            $riskFlags = collect($riskFlags)
                ->reject(fn (array $flag) => $latestActions->get($flag['code'])?->action === 'dismiss')
                ->values()
                ->all();

            if ($canManageRisk) {
                $riskActions = DB::table('student_tracking_risk_actions as actions')
                    ->join('users as actors', 'actors.id', '=', 'actions.actor_id')
                    ->where('actions.student_id', $studentRecord->id)
                    ->orderByDesc('actions.id')
                    ->limit(20)
                    ->get([
                        'actions.risk_code',
                        'actions.action',
                        'actions.note',
                        'actions.created_at',
                        'actors.name as actor_name',
                    ]);
            }
        }

        return view('student-tracking::show', [
            'student' => $studentRecord,
            'courseTrackingAvailable' => $courseTrackingAvailable,
            'attendanceTrackingAvailable' => $attendanceTrackingAvailable,
            'lessonTrackingAvailable' => $lessonTrackingAvailable,
            'contentTrackingAvailable' => $contentTrackingAvailable,
            'examTrackingAvailable' => $examTrackingAvailable,
            'assignmentTrackingAvailable' => $assignmentTrackingAvailable,
            'canManageRisk' => $canManageRisk,
            'enrollments' => $enrollments,
            'announcements' => $announcements,
            'noticeTrackingAvailable' => $noticeTrackingAvailable,
            'supportTickets' => $supportTickets,
            'supportTrackingAvailable' => $supportTrackingAvailable,
            'attendanceRecords' => $attendanceRecords,
            'lessonProgress' => $lessonProgress,
            'contentProgress' => $contentProgress,
            'examAttempts' => $examAttempts,
            'assignmentSubmissions' => $assignmentSubmissions,
            'overdueAssignments' => $overdueAssignments,
            'attendanceRate' => $attendanceRate,
            'riskFlags' => $riskFlags,
            'dismissedRiskFlags' => $dismissedRiskFlags,
            'riskActions' => $riskActions,
            'portalTitle' => $this->portalTitle($actor),
        ]);
    }

    private function addTrackingAggregates(
        Builder $query,
        User $actor,
        PluginResourceAccess $access,
        ModuleRegistry $modules,
        string $fromDate,
        string $toDate
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

        $attendanceTrackingEnabled = $modules->isEnabled('course_management')
            && $modules->isEnabled('batch_management')
            && $modules->isEnabled('attendance');
        $assignmentTrackingEnabled = $modules->isEnabled('course_management')
            && $modules->isEnabled('batch_management')
            && $modules->isEnabled('assignment');
        $lessonTrackingEnabled = $modules->isEnabled('course_management') && $modules->isEnabled('lesson_management');
        $contentTrackingEnabled = $modules->isEnabled('course_management') && $modules->isEnabled('course_content_studio');

        $aggregates = [
            'attendance_total' => $attendanceTrackingEnabled
                ? Attendance::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('attendances.student_id', 'users.id')
                    ->whereIn('batch_id', $scopedBatchIds())
                    ->whereDate('attendance_date', '>=', $fromDate)
                    ->whereDate('attendance_date', '<=', $toDate)
                : DB::raw('0'),
            'attendance_attended' => $attendanceTrackingEnabled
                ? Attendance::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('attendances.student_id', 'users.id')
                    ->whereIn('batch_id', $scopedBatchIds())
                    ->whereDate('attendance_date', '>=', $fromDate)
                    ->whereDate('attendance_date', '<=', $toDate)
                    ->whereIn('status', ['present', 'late'])
                : DB::raw('0'),
            'overdue_assignments_count' => $assignmentTrackingEnabled && Schema::hasTable('assignments') && Schema::hasTable('assignment_submissions')
                ? Assignment::query()
                    ->selectRaw('COUNT(*)')
                    ->whereIn('batch_id', $scopedBatchIds())
                    ->where('status', 'published')
                    ->where('due_at', '<', now())
                    ->whereDoesntHave('submissions', fn (Builder $submissions) => $submissions->whereColumn('student_id', 'users.id'))
                : DB::raw('0'),
            'completed_lessons' => DB::raw('0'),
            'completed_content' => DB::raw('0'),
            'last_lesson_activity' => DB::raw('NULL'),
            'last_content_activity' => DB::raw('NULL'),
        ];

        if ($lessonTrackingEnabled && Schema::hasTable('lesson_progress')) {
            $aggregates['completed_lessons'] = LessonProgress::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('lesson_progress.user_id', 'users.id')
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $scopedCourseIds()))
                ->whereDate('updated_at', '>=', $fromDate)
                ->whereDate('updated_at', '<=', $toDate)
                ->whereNotNull('completed_at');
            $aggregates['last_lesson_activity'] = LessonProgress::query()
                ->selectRaw('MAX(COALESCE(last_viewed_at, completed_at, updated_at))')
                ->whereColumn('lesson_progress.user_id', 'users.id')
                ->whereHas('lesson', fn (Builder $lessons) => $lessons->whereIn('course_id', $scopedCourseIds()));
        }

        if ($contentTrackingEnabled && Schema::hasTable('course_content_progress')) {
            $aggregates['completed_content'] = CourseContentProgress::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('course_content_progress.user_id', 'users.id')
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $scopedCourseIds()))
                ->whereDate('updated_at', '>=', $fromDate)
                ->whereDate('updated_at', '<=', $toDate)
                ->whereNotNull('completed_at');
            $aggregates['last_content_activity'] = CourseContentProgress::query()
                ->selectRaw('MAX(COALESCE(last_viewed_at, completed_at, updated_at))')
                ->whereColumn('course_content_progress.user_id', 'users.id')
                ->whereHas('curriculumItem', fn (Builder $items) => $items->whereIn('course_id', $scopedCourseIds()));
        }

        $query->addSelect($aggregates);
    }

    /**
     * @return list<array{code:string,reason:string}>
     */
    private function additionalRiskSignals(User $student, User $actor, PluginResourceAccess $access, ModuleRegistry $modules): array
    {
        if (! $modules->isEnabled('course_management') || ! $modules->isEnabled('batch_management')) {
            return [];
        }

        $batchIds = $access->scopeQuery(Enrollment::query(), $actor)
            ->where('user_id', $student->id)
            ->whereNotNull('batch_id')
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return [];
        }

        $signals = [];
        $recentStatuses = $modules->isEnabled('course_management') && $modules->isEnabled('batch_management') && $modules->isEnabled('attendance')
            ? Attendance::query()
                ->where('student_id', $student->id)
                ->whereIn('batch_id', $batchIds)
                ->orderByDesc('attendance_date')
                ->orderByDesc('id')
                ->limit(3)
                ->pluck('status')
            : collect();

        if ($recentStatuses->count() === 3 && $recentStatuses->every(fn ($status) => $status === 'absent')) {
            $signals[] = [
                'code' => 'consecutive_absences',
                'reason' => __('The latest three recorded attendance sessions are all marked absent'),
            ];
        }

        $examScores = $modules->isEnabled('course_management') && $modules->isEnabled('batch_management')
            && $modules->isEnabled('exam_management') && Schema::hasTable('exam_attempts') && Schema::hasTable('exam_questions')
            ? ExamAttempt::query()
                ->where('student_id', $student->id)
                ->where('status', 'submitted')
                ->whereNotNull('submitted_at')
                ->whereNotNull('score')
                ->whereHas('exam', fn (Builder $exams) => $exams->whereIn('batch_id', $batchIds))
                ->with(['exam:id,batch_id', 'exam.questions:id,exam_id,marks'])
                ->latest('submitted_at')
                ->limit(3)
                ->get()
                ->map(function (ExamAttempt $attempt): ?array {
                    $maximum = (int) ($attempt->exam?->questions?->sum('marks') ?? 0);
                    if ($maximum < 1) {
                        return null;
                    }

                    return [
                        'at' => $attempt->submitted_at,
                        'percent' => round(((float) $attempt->score / $maximum) * 100, 1),
                    ];
                })
                ->filter()
            : collect();

        $assignmentScores = $modules->isEnabled('course_management') && $modules->isEnabled('batch_management')
            && $modules->isEnabled('assignment') && Schema::hasTable('assignment_submissions')
            ? AssignmentSubmission::query()
                ->where('student_id', $student->id)
                ->where('status', 'graded')
                ->whereNotNull('score')
                ->whereHas('assignment', fn (Builder $assignments) => $assignments->whereIn('batch_id', $batchIds))
                ->with('assignment:id,batch_id,max_marks')
                ->latest('submitted_at')
                ->limit(3)
                ->get()
                ->map(function (AssignmentSubmission $submission): ?array {
                    $maximum = (int) ($submission->assignment?->max_marks ?? 0);
                    if ($maximum < 1) {
                        return null;
                    }

                    return [
                        'at' => $submission->submitted_at,
                        'percent' => round(((float) $submission->score / $maximum) * 100, 1),
                    ];
                })
                ->filter()
            : collect();

        $recentScores = $examScores->concat($assignmentScores)
            ->sortByDesc('at')
            ->take(3)
            ->sortBy('at')
            ->values();

        if ($recentScores->count() === 3) {
            $first = (float) $recentScores->first()['percent'];
            $latest = (float) $recentScores->last()['percent'];
            if ($latest <= $first - 20) {
                $signals[] = [
                    'code' => 'declining_scores',
                    'reason' => __('The latest three graded outcomes show a decline of at least 20 percentage points (from :first% to :latest%)', [
                        'first' => $first,
                        'latest' => $latest,
                    ]),
                ];
            }
        }

        return $signals;
    }

    private function hasGrantedPermission(string $permission): bool
    {
        $plugin = PluginInstallation::query()
            ->where('plugin_key', 'aec.student-tracking')
            ->where('status', 'active')
            ->first();

        $metadata = is_array($plugin?->metadata) ? $plugin->metadata : [];
        $granted = $metadata['granted_permissions'] ?? [];

        return is_array($granted) && in_array($permission, $granted, true);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\\s]*[=+@-]/', $value) === 1 ? "'".$value : $value;
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
