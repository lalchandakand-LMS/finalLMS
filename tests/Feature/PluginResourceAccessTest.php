<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GuardianStudentLink;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name, bool $active = true): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.bin2hex(random_bytes(3)),
            'is_active' => $active,
        ]);
    }

    private function batch(Course $course, User $teacher, Branch $branch, string $title): Batch
    {
        return Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => $title,
            'capacity' => 30,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonths(2),
            'fee' => 3000,
            'status' => 'open',
        ]);
    }

    public function test_branch_manager_is_limited_to_students_and_batches_in_their_active_branch(): void
    {
        $branchA = $this->branch('Branch A');
        $branchB = $this->branch('Branch B');
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $studentA = User::factory()->create(['role' => 'student', 'branch_id' => $branchA->id]);
        $studentB = User::factory()->create(['role' => 'student', 'branch_id' => $branchB->id]);
        $courseA = Course::factory()->create(['teacher_id' => $teacher->id]);
        $courseB = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batchA = $this->batch($courseA, $teacher, $branchA, 'Branch A Batch');
        $batchB = $this->batch($courseB, $teacher, $branchB, 'Branch B Batch');
        $enrollmentA = Enrollment::query()->create(['user_id' => $studentA->id, 'course_id' => $courseA->id, 'batch_id' => $batchA->id, 'status' => 'active']);
        $directEnrollmentA = Enrollment::query()->create(['user_id' => $studentA->id, 'course_id' => $courseA->id, 'status' => 'active']);
        Enrollment::query()->create(['user_id' => $studentB->id, 'course_id' => $courseB->id, 'batch_id' => $batchB->id, 'status' => 'active']);

        $access = app(PluginResourceAccess::class);

        $this->assertTrue($access->allows($manager, $studentA));
        $this->assertFalse($access->allows($manager, $studentB));
        $this->assertTrue($access->allows($manager, $batchA));
        $this->assertFalse($access->allows($manager, $batchB));
        $this->assertTrue($access->allows($manager, $courseA));
        $this->assertFalse($access->allows($manager, $courseB));
        $this->assertSame([$studentA->id], $access->scopeQuery(User::query(), $manager)->pluck('id')->all());
        $this->assertSame([$courseA->id], $access->scopeQuery(Course::query(), $manager)->pluck('id')->all());
        $this->assertSame([$batchA->id], $access->scopeQuery(Batch::query(), $manager)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$enrollmentA->id, $directEnrollmentA->id],
            $access->scopeQuery(Enrollment::query(), $manager)->pluck('id')->all()
        );

        $branchA->update(['is_active' => false]);
        $this->assertFalse($access->allows($manager, $studentA));
        $this->assertFalse($access->allows($manager, $batchA));
        $this->assertFalse($access->allows($manager, $courseA));
    }

    public function test_teacher_and_student_access_is_limited_to_assigned_courses_and_enrollments(): void
    {
        $teacherA = User::factory()->create(['role' => 'teacher']);
        $teacherB = User::factory()->create(['role' => 'teacher']);
        $studentA = User::factory()->create(['role' => 'student']);
        $studentB = User::factory()->create(['role' => 'student']);
        $courseA = Course::factory()->create(['teacher_id' => $teacherA->id]);
        $courseB = Course::factory()->create(['teacher_id' => $teacherB->id]);
        $enrollmentA = Enrollment::query()->create(['user_id' => $studentA->id, 'course_id' => $courseA->id, 'status' => 'active']);
        Enrollment::query()->create(['user_id' => $studentB->id, 'course_id' => $courseB->id, 'status' => 'active']);

        $access = app(PluginResourceAccess::class);

        $this->assertTrue($access->allows($teacherA, $courseA));
        $this->assertFalse($access->allows($teacherA, $courseB));
        $this->assertTrue($access->allows($teacherA, $studentA));
        $this->assertFalse($access->allows($teacherA, $studentB));
        $this->assertTrue($access->allows($studentA, $courseA));
        $this->assertFalse($access->allows($studentA, $courseB));
        $this->assertTrue($access->allows($studentA, $enrollmentA));
        $this->assertFalse($access->allows($studentB, $enrollmentA));
        $this->assertTrue($access->allows($teacherA, $enrollmentA));
        $this->assertSame([$studentA->id], $access->scopeQuery(User::query(), $teacherA)->pluck('id')->all());
        $this->assertSame([$courseA->id], $access->scopeQuery(Course::query(), $teacherA)->pluck('id')->all());
        $this->assertSame([$enrollmentA->id], $access->scopeQuery(Enrollment::query(), $teacherA)->pluck('id')->all());
        $this->assertSame([$courseA->id], $access->scopeQuery(Course::query(), $studentA)->pluck('id')->all());
        $this->assertSame([$enrollmentA->id], $access->scopeQuery(Enrollment::query(), $studentA)->pluck('id')->all());
    }

    public function test_guardian_can_access_only_resources_of_approved_linked_students(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);
        $linkedStudent = User::factory()->create(['role' => 'student']);
        $unlinkedStudent = User::factory()->create(['role' => 'student']);
        GuardianStudentLink::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $linkedStudent->id,
            'relationship' => 'parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $linkedStudent->id,
            'requested_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $branch = $this->branch('Guardian Branch');
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $batch = $this->batch($course, $teacher, $branch, 'Guardian Batch');
        $enrollment = Enrollment::query()->create([
            'user_id' => $linkedStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        $access = app(PluginResourceAccess::class);

        $this->assertTrue($access->allows($guardian, $linkedStudent));
        $this->assertFalse($access->allows($guardian, $unlinkedStudent));
        $this->assertTrue($access->allows($guardian, $course));
        $this->assertTrue($access->allows($guardian, $batch));
        $this->assertTrue($access->allows($guardian, $enrollment));
        $this->assertSame([$linkedStudent->id], $access->scopeQuery(User::query(), $guardian)->pluck('id')->all());
        $this->assertSame([$course->id], $access->scopeQuery(Course::query(), $guardian)->pluck('id')->all());
        $this->assertSame([$batch->id], $access->scopeQuery(Batch::query(), $guardian)->pluck('id')->all());
        $this->assertSame([$enrollment->id], $access->scopeQuery(Enrollment::query(), $guardian)->pluck('id')->all());
    }

    public function test_resource_middleware_uses_bound_models_and_hides_unauthorized_resources(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $otherTeacher->id]);

        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'plugin.resource:course'])
            ->get('/__plugin-scope/courses/{course}', fn (Course $course) => response()->json(['id' => $course->id]));

        $this->actingAs($teacher)->get('/__plugin-scope/courses/'.$course->slug)->assertNotFound();
        $this->actingAs($student)->get('/__plugin-scope/courses/'.$course->slug)->assertNotFound();

        $ownCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $this->actingAs($teacher)->get('/__plugin-scope/courses/'.$ownCourse->slug)->assertOk()->assertJson(['id' => $ownCourse->id]);
    }

    public function test_admin_bypasses_supported_resources_but_unknown_models_are_denied(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $access = app(PluginResourceAccess::class);

        $this->assertTrue($access->allows($admin, $student));
        $this->assertFalse($access->allows($admin, new MediaAsset()));
        $this->assertFalse($access->allows($student, $admin));
    }
}
