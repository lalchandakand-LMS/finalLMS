<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherLessonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_teacher_can_create_update_and_delete_lessons(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);

        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store', $course), [
            'title' => 'Pronunciation',
            'content' => 'Practice vowel sounds.',
            'duration_minutes' => 25,
            'order' => 1,
            'video_url' => 'https://youtu.be/abcdefghijk',
            'is_preview' => '1',
        ])->assertRedirect(route('teacher.courses.lessons.index', $course));

        $lesson = $course->lessons()->firstOrFail();
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'is_preview' => true]);

        $this->put(route('teacher.courses.lessons.update', [$course, $lesson]), [
            'title' => 'Updated pronunciation',
            'content' => 'Updated notes.',
            'duration_minutes' => 30,
            'order' => 2,
            'video_url' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Updated pronunciation']);

        $this->delete(route('teacher.courses.lessons.destroy', [$course, $lesson]))->assertRedirect();
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_teacher_cannot_manage_another_teachers_course_lessons(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('teacher.courses.lessons.index', $course))
            ->assertForbidden();
    }

    public function test_teacher_can_upload_replace_and_delete_a_lesson_video_and_thumbnail(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);

        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store', $course), [
            'title' => 'Uploaded video lesson',
            'content' => 'Lesson notes.',
            'duration_minutes' => 15,
            'order' => 1,
            'video_file' => UploadedFile::fake()->create('lesson.mp4', 100, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('lesson.jpg'),
        ])->assertRedirect(route('teacher.courses.lessons.index', $course));

        $lesson = $course->lessons()->firstOrFail();
        $this->assertNotEmpty($lesson->video_path);
        $this->assertNotEmpty($lesson->thumbnail_path);
        Storage::disk('public')->assertExists($lesson->video_path);
        Storage::disk('public')->assertExists($lesson->thumbnail_path);

        $oldVideo = $lesson->video_path;
        $this->put(route('teacher.courses.lessons.update', [$course, $lesson]), [
            'title' => 'Uploaded video lesson',
            'content' => 'Updated lesson notes.',
            'duration_minutes' => 20,
            'order' => 1,
            'video_file' => UploadedFile::fake()->create('replacement.mp4', 120, 'video/mp4'),
        ])->assertRedirect();

        $lesson->refresh();
        $this->assertNotSame($oldVideo, $lesson->video_path);
        Storage::disk('public')->assertMissing($oldVideo);
        Storage::disk('public')->assertExists($lesson->video_path);

        $videoPath = $lesson->video_path;
        $thumbnailPath = $lesson->thumbnail_path;
        $this->delete(route('teacher.courses.lessons.destroy', [$course, $lesson]))->assertRedirect();
        Storage::disk('public')->assertMissing($videoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
    }

    public function test_lesson_video_upload_rejects_non_video_files(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store', $course), [
            'title' => 'Invalid video',
            'content' => 'No executable uploads.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_file' => UploadedFile::fake()->create('malware.php', 10, 'text/plain'),
        ])->assertSessionHasErrors('video_file');

        $this->assertDatabaseMissing('lessons', ['course_id' => $course->id, 'title' => 'Invalid video']);
    }

}
