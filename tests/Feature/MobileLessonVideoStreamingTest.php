<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileLessonVideoStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_video_stream_uses_private_storage_and_supports_range_requests(): void
    {
        Storage::fake('lesson_videos');
        Storage::disk('lesson_videos')->put('lessons/preview.mp4', '0123456789abcdef');

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['status' => 'published']);
        $lesson = $course->lessons()->create([
            'title' => 'Preview video',
            'content' => 'Watch this sample.',
            'duration_minutes' => 2,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'lessons/preview.mp4',
        ]);
        $token = $this->createStudentToken($student);
        $url = '/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id.'/video';

        $this->withToken($token)->getJson('/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id)
            ->assertOk()
            ->assertJsonPath('lesson.video_stream_path', '/courses/'.$course->slug.'/lessons/'.$lesson->id.'/video')
            ->assertJsonMissingPath('lesson.video_path');

        $this->withToken($token)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_full_lesson_video_requires_enrollment_and_course_lesson_ownership(): void
    {
        Storage::fake('lesson_videos');
        Storage::disk('lesson_videos')->put('lessons/private.mp4', 'private-video');
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = $course->lessons()->create([
            'title' => 'Paid lesson',
            'content' => 'Members only.',
            'duration_minutes' => 20,
            'order' => 1,
            'is_preview' => false,
            'video_path' => 'lessons/private.mp4',
        ]);
        $token = $this->createStudentToken($student);
        $url = '/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id.'/video';

        $this->withToken($token)->get($url)->assertForbidden();
        $this->withToken($token)
            ->get('/api/v1/courses/'.$otherCourse->slug.'/lessons/'.$lesson->id.'/video')
            ->assertNotFound();

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->withToken($token)->get($url)->assertOk();
    }

    public function test_teacher_can_stream_only_lessons_in_their_own_course(): void
    {
        Storage::fake('lesson_videos');
        Storage::disk('lesson_videos')->put('lessons/teacher.mp4', 'teacher-video');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id, 'status' => 'published']);
        $lesson = $course->lessons()->create([
            'title' => 'Teacher lesson',
            'content' => 'Teaching resource.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_path' => 'lessons/teacher.mp4',
        ]);
        $otherLesson = $otherCourse->lessons()->create([
            'title' => 'Other teacher lesson',
            'content' => 'Not yours.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_path' => 'lessons/teacher.mp4',
        ]);
        $token = $this->createTeacherToken($teacher);
        $otherToken = $this->createTeacherToken($otherTeacher);

        $this->withToken($token)->get('/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id.'/video')
            ->assertOk();
        $this->withToken($token)->get('/api/v1/courses/'.$otherCourse->slug.'/lessons/'.$otherLesson->id.'/video')
            ->assertForbidden();
        $this->withToken($otherToken)->get('/api/v1/courses/'.$course->slug.'/lessons/'.$lesson->id.'/video')
            ->assertForbidden();
    }

    public function test_video_stream_rejects_missing_files_and_unsupported_extensions(): void
    {
        Storage::fake('lesson_videos');
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['status' => 'published']);
        $missing = $course->lessons()->create([
            'title' => 'Missing video',
            'content' => 'Missing.',
            'duration_minutes' => 1,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'lessons/missing.mp4',
        ]);
        $unsupported = $course->lessons()->create([
            'title' => 'Unsupported file',
            'content' => 'Not a video.',
            'duration_minutes' => 1,
            'order' => 2,
            'is_preview' => true,
            'video_path' => 'lessons/document.pdf',
        ]);
        Storage::disk('lesson_videos')->put('lessons/document.pdf', 'not a video');
        $token = $this->createStudentToken($student);

        $this->withToken($token)->get('/api/v1/courses/'.$course->slug.'/lessons/'.$missing->id.'/video')
            ->assertNotFound();
        $this->withToken($token)->get('/api/v1/courses/'.$course->slug.'/lessons/'.$unsupported->id.'/video')
            ->assertNotFound();
    }

    private function createStudentToken(User $student): string
    {
        $plainToken = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Student test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $this->storeToken($student, $plainToken, $device);

        return $plainToken;
    }

    private function createTeacherToken(User $teacher): string
    {
        $plainToken = Str::random(80);
        $this->storeToken($teacher, $plainToken);

        return $plainToken;
    }

    private function storeToken(User $user, string $plainToken, ?StudentDevice $device = null): ApiToken
    {
        return $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Mobile video test',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);
    }
}
