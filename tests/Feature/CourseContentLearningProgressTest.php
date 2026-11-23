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
            ->assertSee('Mark as complete');
    }

    public function test_video_progress_autosaves_resume_position_and_auto_completes_at_ninety_percent(): void
    {
        [$course, $post, $student] = $this->fixture();
        $this->actingAs($student)->postJson(route('student.courses.content.progress', [$course, $post]), [
            'position_seconds' => 0, 'duration_seconds' => 100,
        ])->assertOk()->assertJson(['saved' => true, 'progress_percent' => 0, 'completed' => false]);

        foreach ([15, 30, 45] as $position) {
            $this->travel(15)->seconds();
            $this->postJson(route('student.courses.content.progress', [$course, $post]), [
                'position_seconds' => $position, 'duration_seconds' => 100,
            ])->assertOk();
        }
        $this->assertDatabaseHas('course_content_progress', ['user_id' => $student->id, 'progress_percent' => 45, 'watched_seconds' => 45, 'last_position_seconds' => 45, 'duration_seconds' => 100]);

        $this->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('45%');

        foreach ([60, 75, 90, 95] as $position) {
            $this->travel($position === 95 ? 5 : 15)->seconds();
            $response = $this->postJson(route('student.courses.content.progress', [$course, $post]), [
                'position_seconds' => $position, 'duration_seconds' => 100,
            ]);
        }
        $response->assertOk()->assertJson(['completed' => true, 'progress_percent' => 95]);
        $this->assertNotNull(CourseContentProgress::query()->firstOrFail()->completed_at);

        $this->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('100%');
    }

    public function test_seeking_to_the_end_does_not_falsely_complete_a_video_lesson(): void
    {
        [$course, $post, $student] = $this->fixture();
        $this->actingAs($student)->postJson(route('student.courses.content.progress', [$course, $post]), [
            'position_seconds' => 0, 'duration_seconds' => 100,
        ])->assertOk();

        $this->travel(1)->seconds();
        $this->postJson(route('student.courses.content.progress', [$course, $post]), [
            'position_seconds' => 95, 'duration_seconds' => 100,
        ])->assertOk()->assertJson(['completed' => false, 'progress_percent' => 3]);

        $this->assertDatabaseHas('course_content_progress', [
            'user_id' => $student->id,
            'watched_seconds' => 3,
            'completed_at' => null,
        ]);
    }

    public function test_video_lesson_cannot_be_manually_completed_before_ninety_percent_observed_watch_time(): void
    {
        [$course, $post, $student] = $this->fixture();
        $post->update(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->actingAs($student)
            ->from(route('courses.content.show', [$course, $post]))
            ->post(route('student.courses.content.complete', [$course, $post]))
            ->assertRedirect(route('courses.content.show', [$course, $post]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('course_content_progress', 0);
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
