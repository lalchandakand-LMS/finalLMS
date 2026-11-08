<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonMediaProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_lesson_attachment_is_not_available_to_a_non_enrolled_student(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Protected notes',
            'content' => 'Private course notes',
            'duration_minutes' => 30,
            'order' => 1,
            'is_preview' => false,
            'attachment_path' => 'course-media/'.$course->id.'/1/notes.pdf',
            'attachment_name' => 'notes.pdf',
            'attachment_mime' => 'application/pdf',
        ]);
        Storage::disk('local')->put($lesson->attachment_path, '%PDF-private-content');

        $this->actingAs($student)
            ->get(route('courses.lessons.media', [$course, $lesson, 'attachment']))
            ->assertForbidden();
    }

    public function test_course_owner_can_view_private_lesson_video_through_authorized_route(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Protected video',
            'content' => 'Lesson text',
            'duration_minutes' => 30,
            'order' => 1,
            'is_preview' => false,
            'video_path' => 'course-media/'.$course->id.'/1/video.mp4',
            'video_name' => 'video.mp4',
        ]);
        Storage::disk('local')->put($lesson->video_path, 'video-bytes');

        $this->actingAs($teacher)
            ->get(route('courses.lessons.media', [$course, $lesson, 'video']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_public_preview_lesson_attachment_can_be_viewed_without_login(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Preview notes',
            'content' => 'Preview',
            'duration_minutes' => 10,
            'order' => 1,
            'is_preview' => true,
            'attachment_path' => 'course-media/'.$course->id.'/1/preview.pdf',
            'attachment_name' => 'preview.pdf',
            'attachment_mime' => 'application/pdf',
        ]);
        Storage::disk('local')->put($lesson->attachment_path, '%PDF-preview');

        $this->get(route('courses.lessons.media', [$course, $lesson, 'attachment']))
            ->assertOk();
    }
}