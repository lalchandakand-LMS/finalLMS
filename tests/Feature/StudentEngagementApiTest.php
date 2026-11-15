<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Comment;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\Enrollment;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentEngagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_only_receives_student_and_global_active_notices_and_can_mark_them_read(): void
    {
        [$student, $token] = $this->studentToken();
        $visible = DashboardAnnouncement::create([
            'title' => 'Class schedule update', 'body' => 'New schedule is available.',
            'content_type' => 'custom', 'audience' => 'student', 'priority' => 5,
            'is_active' => true, 'created_by' => $student->id,
        ]);
        DashboardAnnouncement::create([
            'title' => 'Teacher only', 'body' => 'Internal notice.',
            'content_type' => 'custom', 'audience' => 'teacher',
            'is_active' => true, 'created_by' => $student->id,
        ]);
        DashboardAnnouncement::create([
            'title' => 'Expired notice', 'body' => 'Old notice.',
            'content_type' => 'custom', 'audience' => 'all',
            'is_active' => true, 'ends_at' => now()->subMinute(), 'created_by' => $student->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/my/notices')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Class schedule update')
            ->assertJsonPath('data.0.is_read', false);

        $this->withToken($token)->postJson('/api/v1/my/notices/'.$visible->id.'/read')
            ->assertOk();
        $this->assertDatabaseHas('dashboard_announcement_reads', [
            'announcement_id' => $visible->id, 'user_id' => $student->id,
        ]);
    }

    public function test_student_cannot_mark_another_audience_or_branch_notice_as_read(): void
    {
        [$student, $token] = $this->studentToken();
        $notice = DashboardAnnouncement::create([
            'title' => 'Teacher notice', 'body' => 'Not for students.',
            'content_type' => 'custom', 'audience' => 'teacher',
            'is_active' => true, 'created_by' => $student->id,
        ]);

        $this->withToken($token)->postJson('/api/v1/my/notices/'.$notice->id.'/read')
            ->assertNotFound();
        $this->assertDatabaseMissing('dashboard_announcement_reads', [
            'announcement_id' => $notice->id, 'user_id' => $student->id,
        ]);
    }

    public function test_instructor_only_receives_teacher_and_global_active_notices_and_can_mark_them_read(): void
    {
        [$teacher, $token] = $this->teacherToken();
        $visible = DashboardAnnouncement::create([
            'title' => 'Teacher training', 'body' => 'Training starts tomorrow.',
            'content_type' => 'custom', 'audience' => 'teacher', 'priority' => 5,
            'is_active' => true, 'created_by' => $teacher->id,
        ]);
        DashboardAnnouncement::create([
            'title' => 'Student only', 'body' => 'Student notice.',
            'content_type' => 'custom', 'audience' => 'student',
            'is_active' => true, 'created_by' => $teacher->id,
        ]);
        DashboardAnnouncement::create([
            'title' => 'Expired teacher notice', 'body' => 'Old notice.',
            'content_type' => 'custom', 'audience' => 'all',
            'is_active' => true, 'ends_at' => now()->subMinute(), 'created_by' => $teacher->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/instructor/notices')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Teacher training')
            ->assertJsonPath('data.0.is_read', false);

        $this->withToken($token)->postJson('/api/v1/instructor/notices/'.$visible->id.'/read')
            ->assertOk();
        $this->assertDatabaseHas('dashboard_announcement_reads', [
            'announcement_id' => $visible->id, 'user_id' => $teacher->id,
        ]);
    }

    public function test_non_teacher_cannot_access_instructor_notices_or_mark_them_read(): void
    {
        [$student, $token] = $this->studentToken();
        $notice = DashboardAnnouncement::create([
            'title' => 'Teacher notice', 'body' => 'Not for students.',
            'content_type' => 'custom', 'audience' => 'teacher',
            'is_active' => true, 'created_by' => $student->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/instructor/notices')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/instructor/notices/'.$notice->id.'/read')
            ->assertForbidden();
    }

    public function test_course_reviews_are_moderated_and_only_enrolled_students_can_submit(): void
    {
        [$student, $token] = $this->studentToken();
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $this->withToken($token)->postJson('/api/v1/my/courses/'.$course->slug.'/reviews', [
            'body' => 'Excellent course and clear lessons.',
        ])->assertForbidden();

        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active',
        ]);

        $this->withToken($token)->postJson('/api/v1/my/courses/'.$course->slug.'/reviews', [
            'body' => 'Excellent course and clear lessons.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('comments', [
            'commentable_type' => $course->getMorphClass(),
            'commentable_id' => $course->id,
            'author_id' => $student->id,
            'status' => 'pending',
        ]);

        $course->comments()->create([
            'author_id' => $student->id,
            'body' => 'Approved public review.',
            'status' => 'approved',
        ]);

        $this->getJson('/api/v1/courses/'.$course->slug.'/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Approved public review.');
    }

    private function teacherToken(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $teacher->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Instructor notice test',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $teacher->id,
            'student_device_id' => $device->id,
            'name' => 'Instructor notice test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$teacher, $plain];
    }

    private function studentToken(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Student engagement test',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'name' => 'Student engagement test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$student, $plain];
    }
}
