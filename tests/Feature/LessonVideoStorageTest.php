<?php

namespace Tests\\Feature;

use App\\Models\\Course;
use App\\Models\\Enrollment;
use App\\Models\\Lesson;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Storage;
use Tests\\TestCase;

class LessonVideoStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_upload_replace_and_delete_private_lesson_video_and_thumbnail(): void
    {
        Storage::fake('public');
        Storage::fake('lesson_videos');

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
        Storage::disk('lesson_videos')->assertExists($lesson->video_path);
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
        Storage::disk('lesson_videos')->assertMissing($oldVideo);
        Storage::disk('lesson_videos')->assertExists($lesson->video_path);

        $videoPath = $lesson->video_path;
        $thumbnailPath = $lesson->thumbnail_path;
        $this->delete(route('teacher.courses.lessons.destroy', [$course, $lesson]))->assertRedirect();
        Storage::disk('lesson_videos')->assertMissing($videoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
    }

    public function test_private_video_streaming_requires_enrollment_unless_the_lesson_is_a_published_preview(): void
    {
        Storage::fake('lesson_videos');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = $course->lessons()->create([
            'title' => 'Members-only video',
            'content' => 'Private lesson.',
            'duration_minutes' => 12,
            'order' => 1,
            'video_path' => 'course-videos/private.mp4',
        ]);
        Storage::disk('lesson_videos')->put($lesson->video_path, 'fake-video-bytes');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $this->actingAs($student)->get(route('courses.lessons.video', [$course, $lesson]))->assertOk();
        $this->actingAs($outsider)->get(route('courses.lessons.video', [$course, $lesson]))->assertForbidden();

        $preview = $course->lessons()->create([
            'title' => 'Free preview',
            'content' => 'Preview lesson.',
            'duration_minutes' => 5,
            'order' => 2,
            'is_preview' => true,
            'video_path' => 'course-videos/preview.mp4',
        ]);
        Storage::disk('lesson_videos')->put($preview->video_path, 'preview-bytes');

        $this->actingAs($outsider)->get(route('courses.lessons.video', [$course, $preview]))->assertOk();

        $draftCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'draft']);
        $draftPreview = $draftCourse->lessons()->create([
            'title' => 'Unpublished preview',
            'content' => 'Must stay private.',
            'duration_minutes' => 5,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'course-videos/draft-preview.mp4',
        ]);
        Storage::disk('lesson_videos')->put($draftPreview->video_path, 'draft-bytes');

        $this->actingAs($outsider)->get(route('courses.lessons.video', [$draftCourse, $draftPreview]))->assertNotFound();
    }

    public function test_lesson_video_upload_rejects_non_video_files(): void
    {
        Storage::fake('public');
        Storage::fake('lesson_videos');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store', $course), [
            'title' => 'Invalid video',
            'content' => 'Executable files must not be accepted.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_file' => UploadedFile::fake()->create('malware.php', 10, 'text/plain'),
        ])->assertSessionHasErrors('video_file');

        $this->assertDatabaseMissing('lessons', ['course_id' => $course->id, 'title' => 'Invalid video']);
    }
}
