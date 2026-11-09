<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_receive_a_hashed_device_token_and_revoke_it(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'mobile-student@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'correct-password',
            'device_name' => 'Student Android',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'student');

        $plainToken = $response->json('access_token');
        $this->assertIsString($plainToken);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $student->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Student Android',
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plainToken]);

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $this->withToken($plainToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Device session revoked.');

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNotNull(ApiToken::query()->firstOrFail()->revoked_at);
    }

    public function test_admin_accounts_cannot_login_to_student_or_instructor_mobile_apps(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'mobile-admin@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
            'device_name' => 'Admin device',
        ])->assertForbidden();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_student_can_revoke_all_mobile_device_sessions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $first = $student->apiTokens()->create([
            'name' => 'Phone',
            'token_hash' => hash('sha256', 'first-device-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);
        $student->apiTokens()->create([
            'name' => 'Tablet',
            'token_hash' => hash('sha256', 'second-device-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('first-device-token-0123456789012345678901234567890123456789')->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseCount('api_tokens', 2);
        $this->assertNotNull($first->fresh()->revoked_at);
        $this->withToken('second-device-token-0123456789012345678901234567890123456789')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_mobile_course_catalog_only_returns_published_courses_and_supports_search(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English Mastery',
            'status' => 'published',
        ]);
        Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Hidden Draft Course',
            'status' => 'draft',
        ]);

        $this->getJson('/api/v1/courses?q=Spoken')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['title' => 'Spoken English Mastery'])
            ->assertJsonMissing(['title' => 'Hidden Draft Course']);
    }

    public function test_student_mobile_my_courses_returns_only_owned_enrollments_and_progress(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $course->lessons()->create([
            'title' => 'First lesson',
            'content' => 'Practice speaking.',
            'duration_minutes' => 20,
            'order' => 1,
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $otherStudent->id, 'course_id' => $course->id, 'status' => 'active']);
        LessonProgress::create(['user_id' => $student->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $token = $student->apiTokens()->create([
            'name' => 'Student app',
            'token_hash' => hash('sha256', 'student-course-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('student-course-token-0123456789012345678901234567890123456789')->getJson('/api/v1/my/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.progress_percent', 100)
            ->assertJsonPath('data.0.course.id', $course->id);
    }

    public function test_course_detail_shows_only_preview_lessons_to_guests_and_full_curriculum_to_enrolled_students(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $preview = $course->lessons()->create([
            'title' => 'Free preview', 'content' => 'Preview text', 'duration_minutes' => 5,
            'order' => 1, 'is_preview' => true,
        ]);
        $locked = $course->lessons()->create([
            'title' => 'Full lesson', 'content' => 'Members only', 'duration_minutes' => 20,
            'order' => 2, 'is_preview' => false,
        ]);

        $this->getJson('/api/v1/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('lessons.0.id', $preview->id)
            ->assertJsonMissing(['id' => $locked->id]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $student->apiTokens()->create([
            'name' => 'Student app',
            'token_hash' => hash('sha256', 'enrolled-course-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('enrolled-course-token-0123456789012345678901234567890123456789')->getJson('/api/v1/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('is_enrolled', true)
            ->assertJsonFragment(['id' => $locked->id, 'title' => 'Full lesson']);
    }

    public function test_student_mobile_api_cannot_open_unenrolled_non_preview_lesson(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $course->lessons()->create([
            'title' => 'Locked lesson',
            'content' => 'Private course content.',
            'duration_minutes' => 15,
            'order' => 1,
            'is_preview' => false,
        ]);
        $student->apiTokens()->create([
            'name' => 'Student app',
            'token_hash' => hash('sha256', 'locked-lesson-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('locked-lesson-token-0123456789012345678901234567890123456789')
            ->getJson('/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id)
            ->assertForbidden();
    }

    public function test_instructor_mobile_apis_only_expose_owned_courses_and_their_students(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $ownCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'My class']);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id, 'title' => 'Other class']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $ownCourse->id, 'status' => 'active']);
        $teacher->apiTokens()->create([
            'name' => 'Instructor app',
            'token_hash' => hash('sha256', 'instructor-owned-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('instructor-owned-token-0123456789012345678901234567890123456789')->getJson('/api/v1/instructor/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['title' => 'My class'])
            ->assertJsonMissing(['title' => 'Other class']);

        $this->withToken('instructor-owned-token-0123456789012345678901234567890123456789')
            ->getJson('/api/v1/instructor/courses/'.$ownCourse->slug.'/students')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student.id', $student->id);

        $this->withToken('instructor-owned-token-0123456789012345678901234567890123456789')
            ->getJson('/api/v1/instructor/courses/'.$otherCourse->slug.'/students')
            ->assertForbidden();
    }

    public function test_teacher_can_access_own_course_lessons_but_not_another_teachers_private_lessons(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $ownCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id]);
        $ownLesson = $ownCourse->lessons()->create([
            'title' => 'Own lesson', 'content' => 'Instructor lesson', 'duration_minutes' => 10, 'order' => 1,
        ]);
        $otherLesson = $otherCourse->lessons()->create([
            'title' => 'Private lesson', 'content' => 'Not yours', 'duration_minutes' => 10, 'order' => 1,
        ]);
        $teacher->apiTokens()->create([
            'name' => 'Instructor app',
            'token_hash' => hash('sha256', 'teacher-lessons-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('teacher-lessons-token-0123456789012345678901234567890123456789')
            ->getJson('/api/v1/courses/'.$ownCourse->slug.'/lessons/'.$ownLesson->id)
            ->assertOk()
            ->assertJsonPath('lesson.title', 'Own lesson');

        $this->withToken('teacher-lessons-token-0123456789012345678901234567890123456789')
            ->getJson('/api/v1/courses/'.$otherCourse->slug.'/lessons/'.$otherLesson->id)
            ->assertForbidden();
    }

    public function test_expired_and_unknown_bearer_tokens_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->apiTokens()->create([
            'name' => 'Expired device',
            'token_hash' => hash('sha256', 'expired-device-token-0123456789012345678901234567890123456789'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken('expired-device-token-0123456789012345678901234567890123456789')->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken('not-a-valid-token-0123456789012345678901234567890123456789')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
