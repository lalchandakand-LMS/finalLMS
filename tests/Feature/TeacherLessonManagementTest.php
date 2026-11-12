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


    public function test_uploaded_lesson_video_is_only_streamed_to_preview_or_authorized_course_members(): void
    {
        Storage::fake('lesson_videos');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = $course->lessons()->create([
            'title' => 'Private lesson video',
            'content' => 'Members only.',
            'duration_minutes' => 12,
            'order' => 1,
            'video_path' => 'course-videos/private.mp4',
        ]);
        Storage::disk('lesson_videos')->put($lesson->video_path, 'fake-video-bytes');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $this->actingAs($student)->get(route('courses.lessons.video', [$course, $lesson]))->assertOk();
        $this->actingAs($outsider)->get(route('courses.lessons.video', [$course, $lesson]))->assertForbidden();

        $draftCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'draft']);
        $previewLesson = $draftCourse->lessons()->create([
            'title' => 'Draft preview must stay private',
            'content' => 'Not published yet.',
            'duration_minutes' => 10,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'course-videos/draft-preview.mp4',
        ]);
        Storage::disk('lesson_videos')->put($previewLesson->video_path, 'private-draft-bytes');
        $this->actingAs($outsider)->get(route('courses.lessons.video', [$draftCourse, $previewLesson]))->assertNotFound();
    }


    public function test_published_preview_video_is_public_but_non_preview_video_is_protected(): void
    {
        Storage::fake('lesson_videos');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $preview = $course->lessons()->create([
            'title' => 'Free preview',
            'content' => 'Public sample.',
            'duration_minutes' => 5,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'course-videos/public-preview.mp4',
        ]);
        $private = $course->lessons()->create([
            'title' => 'Paid lesson',
            'content' => 'Enrolled students only.',
            'duration_minutes' => 10,
            'order' => 2,
            'is_preview' => false,
            'video_path' => 'course-videos/private-lesson.mp4',
        ]);
        Storage::disk('lesson_videos')->put($preview->video_path, 'preview-bytes');
        Storage::disk('lesson_videos')->put($private->video_path, 'private-bytes');

        $response = $this->get(route('courses.lessons.video', [$course, $preview]));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $this->get(route('courses.lessons.video', [$course, $private]))->assertForbidden();
    }

    public function test_lesson_video_route_rejects_a_lesson_from_another_course(): void
    {
        Storage::fake('lesson_videos');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $lesson = $otherCourse->lessons()->create([
            'title' => 'Different course lesson',
            'content' => 'Must not be reachable through another course URL.',
            'duration_minutes' => 10,
            'order' => 1,
            'is_preview' => true,
            'video_path' => 'course-videos/other-course.mp4',
        ]);
        Storage::disk('lesson_videos')->put($lesson->video_path, 'private-bytes');

        $this->get(route('courses.lessons.video', [$course, $lesson]))->assertNotFound();
    }

}
