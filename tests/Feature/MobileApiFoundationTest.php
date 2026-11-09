<?php

namespace Tests\\Feature;

use App\\Models\\ApiToken;
use App\\Models\\Course;
use App\\Models\\Enrollment;
use App\\Models\\LessonProgress;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Hash;
use Tests\\TestCase;

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
            'token_hash' => hash('sha256', 'first-device-token'),
            'expires_at' => now()->addDay(),
        ]);
        $student->apiTokens()->create([
            'name' => 'Tablet',
            'token_hash' => hash('sha256', 'second-device-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('first-device-token')->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseCount('api_tokens', 2);
        $this->assertNotNull($first->fresh()->revoked_at);
        $this->withToken('second-device-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
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
            'token_hash' => hash('sha256', 'student-course-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('student-course-token')->getJson('/api/v1/my/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.progress_percent', 100)
            ->assertJsonPath('data.0.course.id', $course->id);
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
            'token_hash' => hash('sha256', 'locked-lesson-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken('locked-lesson-token')
            ->getJson('/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id)
            ->assertForbidden();
    }

    public function test_expired_and_unknown_bearer_tokens_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->apiTokens()->create([
            'name' => 'Expired device',
            'token_hash' => hash('sha256', 'expired-device-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken('expired-device-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken('not-a-valid-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
