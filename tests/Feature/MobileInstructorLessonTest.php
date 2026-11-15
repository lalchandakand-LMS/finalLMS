<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileInstructorLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_list_create_update_and_delete_lessons_for_owned_course(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'draft']);
        $token = $this->token($teacher);

        $this->withToken($token)->getJson('/api/v1/instructor/courses/'.$course->slug.'/lessons')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $created = $this->withToken($token)->postJson('/api/v1/instructor/courses/'.$course->slug.'/lessons', [
            'title' => 'English pronunciation',
            'content' => 'Learn vowel sounds and practice with examples.',
            'duration_minutes' => 25,
            'order' => 1,
            'video_url' => 'https://youtu.be/abcdefghijk',
            'is_preview' => true,
        ])->assertCreated()
            ->assertJsonPath('lesson.title', 'English pronunciation')
            ->assertJsonPath('lesson.is_preview', true)
            ->assertJsonPath('lesson.video_url', 'https://youtu.be/abcdefghijk');

        $lessonId = $created->json('lesson.id');
        $lesson = Lesson::findOrFail($lessonId);

        $this->withToken($token)->getJson('/api/v1/instructor/courses/'.$course->slug.'/lessons')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $lessonId);

        $this->withToken($token)->putJson('/api/v1/instructor/courses/'.$course->slug.'/lessons/'.$lessonId, [
            'title' => 'Pronunciation — updated',
            'content' => 'Updated lesson notes.',
            'duration_minutes' => 30,
            'order' => 2,
            'video_url' => '',
            'is_preview' => false,
        ])->assertOk()
            ->assertJsonPath('lesson.title', 'Pronunciation — updated')
            ->assertJsonPath('lesson.order', 2)
            ->assertJsonPath('lesson.video_url', null)
            ->assertJsonPath('lesson.is_preview', false);

        $this->withToken($token)->deleteJson('/api/v1/instructor/courses/'.$course->slug.'/lessons/'.$lessonId)
            ->assertOk()
            ->assertJsonPath('message', 'Lesson deleted successfully.');

        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
    }

    public function test_instructor_cannot_manage_another_teachers_course_or_foreign_lesson(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $owner->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $other->id]);
        $lesson = $course->lessons()->create([
            'title' => 'Owner lesson',
            'content' => 'Private content.',
            'duration_minutes' => 10,
            'order' => 1,
        ]);
        $token = $this->token($other);

        $this->withToken($token)->getJson('/api/v1/instructor/courses/'.$course->slug.'/lessons')
            ->assertNotFound();

        $this->withToken($token)->putJson('/api/v1/instructor/courses/'.$otherCourse->slug.'/lessons/'.$lesson->id, [
            'title' => 'Cross-course attack',
            'content' => 'Should not update.',
            'duration_minutes' => 10,
            'order' => 1,
        ])->assertNotFound();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Owner lesson']);
    }

    public function test_students_cannot_use_instructor_lesson_authoring_and_invalid_video_hosts_are_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $payload = [
            'title' => 'Secure lesson',
            'content' => 'Lesson notes.',
            'duration_minutes' => 15,
            'order' => 1,
        ];

        $this->withToken($this->token($student))
            ->postJson('/api/v1/instructor/courses/'.$course->slug.'/lessons', $payload)
            ->assertForbidden();

        $this->withToken($this->token($teacher))
            ->postJson('/api/v1/instructor/courses/'.$course->slug.'/lessons', [
                ...$payload,
                'video_url' => 'https://evil.example/video',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('video_url');

        $this->assertDatabaseMissing('lessons', ['course_id' => $course->id, 'title' => 'Secure lesson']);
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = null;

        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Instructor lesson test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Instructor lesson test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
