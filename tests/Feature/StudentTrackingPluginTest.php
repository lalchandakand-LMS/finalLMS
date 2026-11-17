<?php

namespace Tests\Feature;

use Aloms\StudentTracking\StudentTrackingController;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GuardianStudentLink;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/plugins/aec/student-tracking/1.0.0/src/StudentTrackingController.php';

class StudentTrackingPluginTest extends TestCase
{
    use RefreshDatabase;

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
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
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

        $view = $this->showFor($teacher, $student->id);

        $this->assertSame([$ownCourse->id], $view->getData()['enrollments']->pluck('course_id')->all());
        $this->assertSame([$ownBatch->id], $view->getData()['attendanceRecords']->pluck('batch_id')->unique()->values()->all());

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
