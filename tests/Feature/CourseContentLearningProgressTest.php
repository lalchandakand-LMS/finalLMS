<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\CourseContentProgress;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentLearningProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_track_complete_and_resume_a_course_studio_lesson(): void
    {
        [$course, $post, $student] = $this->fixture();

        $this->actingAs($student)
            ->get(route('courses.content.show', [$course, $post]))
            ->assertOk()
            ->assertSee('Mark lesson complete');

        $progress = CourseContentProgress::query()->firstOrFail();
        $this->assertSame($student->id, $progress->user_id);
        $this->assertNotNull($progress->last_viewed_at);
        $this->assertNull($progress->completed_at);

        $this->post(route('student.courses.content.complete', [$course, $post]))
            ->assertRedirect(route('courses.content.show', [$course, $post]))
            ->assertSessionHas('success');

        $this->assertNotNull($progress->fresh()->completed_at);

        $this->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Review last lesson');
    }

    public function test_course_completion_requires_all_published_curriculum_posts_and_issues_certificate_afterward(): void
    {
        [$course, $firstPost, $student] = $this->fixture();
        $secondPost = $this->publishedPost($student, 'Second lesson');
        $course->curriculumItems()->create([
            'course_content_post_id' => $secondPost->id,
            'section_title' => 'Module 2',
            'position' => 20,
            'is_preview' => false,
        ]);

        $this->actingAs($student)
            ->post(route('courses.complete', $course))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->post(route('student.courses.content.complete', [$course, $firstPost]));
        $this->post(route('student.courses.content.complete', [$course, $secondPost]));
        $this->post(route('courses.complete', $course))->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'issued',
        ]);
    }

    public function test_unenrolled_student_can_preview_but_cannot_record_or_complete_preview_progress(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $post = $this->publishedPost($teacher, 'Free preview');
        $course->curriculumItems()->create([
            'course_content_post_id' => $post->id,
            'position' => 10,
            'is_preview' => true,
        ]);

        $this->actingAs($student)
            ->get(route('courses.content.show', [$course, $post]))
            ->assertOk()
            ->assertDontSee('Mark lesson complete');

        $this->assertDatabaseCount('course_content_progress', 0);

        $this->post(route('student.courses.content.complete', [$course, $post]))
            ->assertForbidden();

        $this->assertDatabaseCount('course_content_progress', 0);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $post = $this->publishedPost($teacher, 'Course Studio lesson');
        $course->curriculumItems()->create([
            'course_content_post_id' => $post->id,
            'section_title' => 'Module 1',
            'position' => 10,
            'is_preview' => false,
        ]);

        return [$course, $post, $student];
    }

    private function publishedPost(User $author, string $title): CourseContentPost
    {
        return CourseContentPost::create([
            'author_id' => $author->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'long_description' => 'Lesson body',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
    }
}
