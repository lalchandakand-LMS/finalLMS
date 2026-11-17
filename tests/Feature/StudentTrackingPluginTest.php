<?php

namespace Tests\Feature;

use Aloms\StudentTracking\StudentTrackingController;
use Aloms\StudentTracking\StudentTrackingSettingsController;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GuardianStudentLink;
use App\Models\Setting;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/plugins/aec/student-tracking/1.0.0/src/StudentTrackingController.php';
require_once dirname(__DIR__, 2).'/plugins/aec/student-tracking/1.0.0/src/StudentTrackingSettingsController.php';

class StudentTrackingPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('student_tracking_risk_actions')) {
            $migration = require base_path('plugins/aec/student-tracking/1.0.0/migrations/2026_11_17_180000_create_student_tracking_risk_actions.php');
            $migration->up();
        }
    }

    private function indexFor(User $actor, array $query = [])
    {
        // These controller-level tests bypass PluginRuntime, so mirror the view
        // namespace registration performed by the signed plugin entrypoint.
        View::addNamespace('student-tracking', base_path('plugins/aec/student-tracking/1.0.0/resources/views'));

        $request = Request::create('/student-tracking', 'GET', $query);
        $request->setUserResolver(fn () => $actor);

        return app(StudentTrackingController::class)->index($request, app(PluginResourceAccess::class));
    }

    private function showFor(User $actor, int $studentId)
    {
        View::addNamespace('student-tracking', base_path('plugins/aec/student-tracking/1.0.0/resources/views'));

        $request = Request::create('/student-tracking/'.$studentId, 'GET');
        $request->setUserResolver(fn () => $actor);

        return app(StudentTrackingController::class)->show(
            $request,
            $studentId,
            app(PluginResourceAccess::class)
        );
    }

    private function exportFor(User $actor, array $query = [])
    {
        $request = Request::create('/student-tracking/export', 'GET', $query);
        $request->setUserResolver(fn () => $actor);

        return app(StudentTrackingController::class)->export($request, app(PluginResourceAccess::class));
    }

    private function riskActionFor(User $actor, int $studentId, array $data)
    {
        $request = Request::create('/student-tracking/'.$studentId.'/risk-actions', 'POST', $data);
        $request->setUserResolver(fn () => $actor);

        return app(StudentTrackingController::class)->storeRiskAction(
            $request,
            $studentId,
            app(PluginResourceAccess::class)
        );
    }

    public function test_admin_can_configure_and_audit_student_tracking_risk_thresholds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = Request::create('/admin/student-tracking/settings', 'PUT', [
            'low_attendance_percent' => 68,
            'minimum_attendance_records' => 4,
            'inactive_days' => 21,
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(StudentTrackingSettingsController::class)->update($request);

        $this->assertSame(68, (int) Setting::value('student_tracking_low_attendance_percent'));
        $this->assertSame(4, (int) Setting::value('student_tracking_minimum_attendance_records'));
        $this->assertSame(21, (int) Setting::value('student_tracking_inactive_days'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'student_tracking.risk_rules_updated',
        ]);
        $this->assertSame('Student tracking risk rules updated.', $response->getSession()->get('success'));
    }

    public function test_non_admin_cannot_change_student_tracking_risk_thresholds(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $request = Request::create('/admin/student-tracking/settings', 'PUT', [
            'low_attendance_percent' => 50,
            'minimum_attendance_records' => 2,
            'inactive_days' => 7,
        ]);
        $request->setUserResolver(fn () => $teacher);

        $this->expectException(\\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StudentTrackingSettingsController::class)->update($request);
    }

    public function test_teacher_can_dismiss_a_scoped_risk_flag_with_audited_note(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::create(['name' => 'Risk scope', 'slug' => 'risk-scope', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Risk batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        $this->riskActionFor($teacher, $student->id, [
            'risk_code' => 'inactive_learning',
            'action' => 'dismiss',
            'note' => 'Follow-up call scheduled with guardian.',
        ]);

        $index = $this->indexFor($teacher);
        $this->assertFalse($index->getData()['students']->first()->tracking_needs_attention);

        $detail = $this->showFor($teacher, $student->id);
        $this->assertSame(['inactive_learning'], array_column($detail->getData()['dismissedRiskFlags'], 'code'));
        $this->assertSame('Follow-up call scheduled with guardian.', $detail->getData()['riskActions']->first()->note);

        $guardian = User::factory()->create(['role' => 'guardian']);
        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $student->id,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);
        $guardianView = $this->showFor($guardian, $student->id);
        $this->assertCount(0, $guardianView->getData()['riskActions']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $teacher->id,
            'event' => 'student_tracking.risk_action',
        ]);
    }

    public function test_student_cannot_write_staff_risk_actions(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->expectException(\\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->riskActionFor($student, $student->id, [
            'risk_code' => 'inactive_learning',
            'action' => 'dismiss',
            'note' => 'Not allowed.',
        ]);
    }

    public function test_admin_can_view_all_students_and_tracking_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Central', 'slug' => 'central', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $batch = \App\Models\Batch::create([
            'branch_id' => $branch->id,
            'course_id' => Course::factory()->create()->id,
            'teacher_id' => $admin->id,
            'title' => 'Morning Batch',
            'capacity' => 30,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        Attendance::create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'marked_by' => $admin->id,
            'attendance_date' => today(),
            'status' => 'present',
        ]);

        $view = $this->indexFor($admin);

        $this->assertSame('student-tracking::index', $view->name());
        $this->assertSame(1, $view->getData()['summary']['students']);
        $this->assertSame(1, $view->getData()['summary']['active_enrollments']);
        $this->assertSame(1, $view->getData()['summary']['attendance_today']);
        $this->assertSame($student->id, $view->getData()['students']->first()->id);
        $this->assertSame(100.0, (float) $view->getData()['students']->first()->attendance_rate);
    }

    public function test_dashboard_remains_available_when_optional_progress_tables_are_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student']);

        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('course_content_progress');

        $view = $this->indexFor($admin);
        $student = $view->getData()['students']->first();

        $this->assertSame(0, (int) $student->completed_lessons);
        $this->assertSame(0, (int) $student->completed_content);
        $this->assertNull($student->last_lesson_activity);
        $this->assertNull($student->last_content_activity);
    }

    public function test_branch_manager_only_sees_students_from_their_active_branch(): void
    {
        $branch = Branch::create(['name' => 'North', 'slug' => 'north', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'South', 'slug' => 'south', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $inside = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        User::factory()->create(['role' => 'student', 'branch_id' => $otherBranch->id]);

        $view = $this->indexFor($manager);
        $students = $view->getData()['students']->getCollection();

        $this->assertCount(1, $students);
        $this->assertSame($inside->id, $students->first()->id);
    }

    public function test_teacher_only_sees_students_enrolled_in_their_courses_or_batches(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id]);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $otherStudent->id, 'course_id' => $otherCourse->id, 'status' => 'active']);

        $view = $this->indexFor($teacher);
        $students = $view->getData()['students']->getCollection();

        $this->assertCount(1, $students);
        $this->assertSame($student->id, $students->first()->id);
    }

    public function test_guardian_only_sees_students_with_an_approved_active_link(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $linked = User::factory()->create(['role' => 'student']);
        User::factory()->create(['role' => 'student']);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $linked->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $linked->id,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $view = $this->indexFor($guardian);
        $students = $view->getData()['students']->getCollection();

        $this->assertCount(1, $students);
        $this->assertSame($linked->id, $students->first()->id);
    }

    public function test_teacher_batch_filter_cannot_select_a_student_through_another_teachers_batch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::create(['name' => 'Scoped', 'slug' => 'scoped', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $ownCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id]);

        $ownBatch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $ownCourse->id,
            'teacher_id' => $teacher->id,
            'title' => 'Own batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        $otherBatch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $otherCourse->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other teacher batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $ownCourse->id, 'batch_id' => $ownBatch->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $otherCourse->id, 'batch_id' => $otherBatch->id, 'status' => 'active']);

        $unfiltered = $this->indexFor($teacher);
        $this->assertSame($student->id, $unfiltered->getData()['students']->first()->id);

        $filtered = $this->indexFor($teacher, ['batch_id' => $otherBatch->id]);
        $this->assertCount(0, $filtered->getData()['students']->getCollection());
    }

    public function test_teacher_student_detail_only_includes_their_accessible_courses_and_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Detail scope', 'slug' => 'detail-scope', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id, 'name' => '=HYPERLINK("https://example.invalid")']);
        $ownCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id]);

        $ownBatch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $ownCourse->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assigned batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        $otherBatch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $otherCourse->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Unassigned batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $ownCourse->id, 'batch_id' => $ownBatch->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $otherCourse->id, 'batch_id' => $otherBatch->id, 'status' => 'active']);
        Attendance::create([
            'batch_id' => $ownBatch->id,
            'student_id' => $student->id,
            'marked_by' => $admin->id,
            'attendance_date' => today(),
            'status' => 'present',
        ]);
        Attendance::create([
            'batch_id' => $otherBatch->id,
            'student_id' => $student->id,
            'marked_by' => $admin->id,
            'attendance_date' => today(),
            'status' => 'absent',
        ]);
        Attendance::create([
            'batch_id' => $ownBatch->id,
            'student_id' => $student->id,
            'marked_by' => $admin->id,
            'attendance_date' => today()->subDay(),
            'status' => 'absent',
        ]);

        $ownExam = Exam::create([
            'batch_id' => $ownBatch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assigned assessment',
            'instructions' => 'Complete the assessment.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 10,
            'status' => 'published',
        ]);
        $otherExam = Exam::create([
            'batch_id' => $otherBatch->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Unassigned assessment',
            'instructions' => 'Private assessment.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 10,
            'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $ownExam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
            'score' => 18,
            'status' => 'submitted',
        ]);
        ExamAttempt::create([
            'exam_id' => $otherExam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
            'score' => 2,
            'status' => 'submitted',
        ]);

        $ownAssignment = Assignment::create([
            'batch_id' => $ownBatch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assigned homework',
            'instructions' => 'Complete the homework.',
            'due_at' => now()->addDay(),
            'max_marks' => 20,
            'status' => 'published',
        ]);
        $otherAssignment = Assignment::create([
            'batch_id' => $otherBatch->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Unassigned homework',
            'instructions' => 'Private homework.',
            'due_at' => now()->addDay(),
            'max_marks' => 20,
            'status' => 'published',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $ownAssignment->id,
            'student_id' => $student->id,
            'answer' => 'My work',
            'submitted_at' => now()->subHour(),
            'score' => 15,
            'feedback' => 'Private teacher feedback.',
            'graded_by' => $teacher->id,
            'status' => 'graded',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $otherAssignment->id,
            'student_id' => $student->id,
            'answer' => 'Other work',
            'submitted_at' => now()->subHour(),
            'score' => 1,
            'feedback' => 'Other private feedback.',
            'graded_by' => $otherTeacher->id,
            'status' => 'graded',
        ]);

        $view = $this->showFor($teacher, $student->id);

        $this->assertSame([$ownCourse->id], $view->getData()['enrollments']->pluck('course_id')->all());
        $this->assertSame([$ownBatch->id], $view->getData()['attendanceRecords']->pluck('batch_id')->unique()->values()->all());
        $this->assertSame([$ownExam->id], $view->getData()['examAttempts']->pluck('exam_id')->all());
        $this->assertSame([$ownAssignment->id], $view->getData()['assignmentSubmissions']->pluck('assignment_id')->all());
        $this->assertArrayNotHasKey('feedback', $view->getData()['assignmentSubmissions']->first()->getAttributes());
        $this->assertArrayNotHasKey('answer', $view->getData()['assignmentSubmissions']->first()->getAttributes());

        $index = $this->indexFor($teacher);
        $trackedStudent = $index->getData()['students']->first();
        $this->assertSame(1, $index->getData()['summary']['attendance_today']);
        $this->assertSame(2, (int) $trackedStudent->attendance_total);
        $this->assertSame(50.0, (float) $trackedStudent->attendance_rate);
        $this->assertSame(1, (int) $trackedStudent->active_enrollments_count);

        $todayOnly = $this->indexFor($teacher, [
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
            'status' => 'active',
        ]);
        $todayStudent = $todayOnly->getData()['students']->first();
        $this->assertSame(1, (int) $todayStudent->attendance_total);
        $this->assertSame(100.0, (float) $todayStudent->attendance_rate);

        $noMatchingStatus = $this->indexFor($teacher, ['status' => 'completed']);
        $this->assertCount(0, $noMatchingStatus->getData()['students']->getCollection());
        $this->assertContains(
            'No recorded LMS learning activity in the last 14 days',
            $trackedStudent->tracking_risk_reasons
        );

        $export = $this->exportFor($teacher, [
            'from_date' => today()->subDays(2)->toDateString(),
            'to_date' => today()->toDateString(),
        ]);
        ob_start();
        $export->sendContent();
        $csv = (string) ob_get_clean();
        $this->assertStringContainsString('student_id,student_name,attendance_rate_percent', $csv);
        $this->assertStringContainsString("'=HYPERLINK(\"https://example.invalid\")", $csv);
        $this->assertStringContainsString(',50,2,1,1,0,0,', $csv);
        $this->assertStringNotContainsString('Unassigned batch', $csv);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $teacher->id,
            'event' => 'student_tracking.exported',
        ]);
    }

    public function test_guardian_loses_tracking_access_after_link_is_revoked(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $student = User::factory()->create(['role' => 'student']);
        $link = GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $student->id,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->assertCount(1, $this->indexFor($guardian)->getData()['students']->getCollection());

        $link->update(['status' => 'revoked']);

        $view = $this->indexFor($guardian);
        $this->assertCount(0, $view->getData()['students']->getCollection());
    }

    public function test_student_only_sees_their_own_tracking_record(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        User::factory()->create(['role' => 'student']);

        $view = $this->indexFor($student);
        $students = $view->getData()['students']->getCollection();

        $this->assertCount(1, $students);
        $this->assertSame($student->id, $students->first()->id);
    }
}
