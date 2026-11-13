<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_mobile_login_requires_sms_otp_before_issuing_a_device_bound_token(): void
    {
        config([
            'services.twilio.sid' => 'AC-test-account',
            'services.twilio.token' => 'test-auth-token',
            'services.twilio.from' => '+15005550006',
        ]);
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;

            return Http::response(['sid' => 'SM-test'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'mobile-student@example.test',
            'password' => Hash::make('correct-password'),
            'phone_number' => '+8801712345678',
        ]);
        $deviceId = str_repeat('a', 64);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'correct-password',
            'device_name' => 'Student Android',
            'device_id' => $deviceId,
        ])->assertStatus(202)
            ->assertJsonPath('status', 'phone_verification_required');

        $challenge = $login->json('challenge_token');
        $device = StudentDevice::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending_phone_verification', $device->status);
        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertNotNull($code);

        $verified = $this->postJson('/api/v1/auth/verify-device', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'student');

        $plainToken = $verified->json('access_token');
        $this->assertIsString($plainToken);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Student Android',
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plainToken]);
        $this->assertSame('approved', $device->fresh()->status);

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $this->withToken($plainToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Device session revoked.');

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNotNull(ApiToken::query()->firstOrFail()->revoked_at);
    }


    public function test_additional_mobile_device_requires_admin_approval_and_invalidates_previous_app_token(): void
    {
        config([
            'services.twilio.sid' => 'AC-test-account',
            'services.twilio.token' => 'test-auth-token',
            'services.twilio.from' => '+15005550006',
        ]);
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;

            return Http::response(['sid' => 'SM-test'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
        ]);
        $oldToken = $this->createStudentApiToken(
            $student,
            'old-approved-device-token-0123456789012345678901234567890123456789'
        );
        $newDeviceId = str_repeat('d', 64);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'New Android phone',
            'device_id' => $newDeviceId,
        ])->assertStatus(202)->assertJsonPath('status', 'phone_verification_required');

        $challenge = $login->json('challenge_token');
        $newDevice = StudentDevice::query()
            ->where('user_id', $student->id)
            ->where('device_hash', hash('sha256', $newDeviceId))
            ->firstOrFail();

        $this->postJson('/api/v1/auth/verify-device', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertStatus(202)
            ->assertJsonPath('status', 'pending_admin_approval');

        $this->postJson('/api/v1/auth/device-status', ['challenge_token' => $challenge])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending_admin_approval');

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('admin.student-devices.approve', $newDevice))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('revoked', $oldToken->studentDevice->fresh()->status);
        $this->assertNotNull($oldToken->fresh()->revoked_at);

        auth()->logout();
        $tokenResponse = $this->postJson('/api/v1/auth/device-status', ['challenge_token' => $challenge])
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $newToken = $tokenResponse->json('access_token');
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $student->id,
            'student_device_id' => $newDevice->id,
            'token_hash' => hash('sha256', $newToken),
            'revoked_at' => null,
        ]);
        $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_student_api_rejects_legacy_tokens_that_are_not_bound_to_an_approved_device(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $plainToken = 'legacy-student-token-0123456789012345678901234567890123456789';
        $token = $student->apiTokens()->create([
            'name' => 'Legacy device',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNotNull($token->fresh()->revoked_at);
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
            'device_id' => str_repeat('f', 64),
        ])->assertForbidden();

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_student_can_revoke_all_mobile_device_sessions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $first = $this->createStudentApiToken($student, 'first-device-token-0123456789012345678901234567890123456789', 'Phone');
        $this->createStudentApiToken($student, 'second-device-token-0123456789012345678901234567890123456789', 'Phone', $first->studentDevice);

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
        $token = $this->createStudentApiToken($student, 'student-course-token-0123456789012345678901234567890123456789');

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
        $this->createStudentApiToken($student, 'enrolled-course-token-0123456789012345678901234567890123456789');

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
        $this->createStudentApiToken($student, 'locked-lesson-token-0123456789012345678901234567890123456789');

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

    public function test_enrolled_student_can_complete_lesson_through_mobile_api_idempotently(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $course->lessons()->create([
            'title' => 'Complete from app', 'content' => 'Lesson', 'duration_minutes' => 10, 'order' => 1,
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $this->createStudentApiToken($student, 'mobile-completion-token-0123456789012345678901234567890123456789');
        $url = '/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id.'/complete';

        $this->withToken('mobile-completion-token-0123456789012345678901234567890123456789')
            ->postJson($url)->assertOk()->assertJsonPath('completed', true);
        $this->withToken('mobile-completion-token-0123456789012345678901234567890123456789')
            ->postJson($url)->assertOk()->assertJsonPath('completed', true);

        $this->assertDatabaseCount('lesson_progress', 1);
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id]);
    }

    public function test_expired_and_unknown_bearer_tokens_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->createStudentApiToken($student, 'expired-device-token-0123456789012345678901234567890123456789', 'Expired device', null, now()->subMinute());

        $this->withToken('expired-device-token-0123456789012345678901234567890123456789')->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken('not-a-valid-token-0123456789012345678901234567890123456789')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
    private function createStudentApiToken(
        User $student,
        string $plainToken,
        string $name = 'Student app',
        ?StudentDevice $device = null,
        ?\DateTimeInterface $expiresAt = null
    ): ApiToken {
        $device ??= StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => $name,
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);

        return $student->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }

}
