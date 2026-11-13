<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_catalog_filters_by_visible_content_category(): void
    {
        $category = CourseCategory::create([
            'name' => 'Spoken English',
            'slug' => 'spoken-english',
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $matchingCourse = Course::factory()->create(['title' => 'Spoken English Foundation']);
        $otherCourse = Course::factory()->create(['title' => 'Grammar Mastery']);
        $post = CourseContentPost::create([
            'author_id' => $matchingCourse->teacher_id,
            'title' => 'Speaking practice module',
            'slug' => 'speaking-practice-module',
            'long_description' => 'Practice speaking with guided lessons.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $post->categories()->attach($category);
        $matchingCourse->curriculumItems()->create([
            'course_content_post_id' => $post->id,
            'position' => 1,
            'is_preview' => true,
        ]);
        $draftPost = CourseContentPost::create([
            'author_id' => $otherCourse->teacher_id,
            'title' => 'Draft grammar module',
            'slug' => 'draft-grammar-module',
            'long_description' => 'This draft must not appear in public category filters.',
            'status' => 'draft',
            'published_at' => now(),
        ]);
        $draftPost->categories()->attach($category);
        $otherCourse->curriculumItems()->create([
            'course_content_post_id' => $draftPost->id,
            'position' => 1,
            'is_preview' => true,
        ]);

        $this->get(route('courses.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('Spoken English Foundation')
            ->assertDontSee('Grammar Mastery');
    }

    public function test_course_detail_shows_a_published_preview_video_as_demo_class(): void
    {
        $course = Course::factory()->create(['title' => 'Demo English Course']);
        $post = CourseContentPost::create([
            'author_id' => $course->teacher_id,
            'title' => 'First demo lesson',
            'slug' => 'first-demo-lesson',
            'short_description' => 'A sample lesson for new students.',
            'long_description' => 'Welcome to the sample lesson.',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $course->curriculumItems()->create([
            'course_content_post_id' => $post->id,
            'position' => 1,
            'is_preview' => true,
        ]);
        $scheduledPost = CourseContentPost::create([
            'author_id' => $course->teacher_id,
            'title' => 'Future demo lesson',
            'slug' => 'future-demo-lesson',
            'long_description' => 'This lesson is not published yet.',
            'video_url' => 'https://youtu.be/9bZkp7q19f0',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);
        $course->curriculumItems()->create([
            'course_content_post_id' => $scheduledPost->id,
            'position' => 2,
            'is_preview' => true,
        ]);

        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Demo Class')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->assertDontSee('Future demo lesson');
    }

    public function test_guest_opening_a_locked_course_post_is_sent_to_login_and_back_to_course(): void
    {
        $course = Course::factory()->create(['title' => 'Protected Course']);
        $post = CourseContentPost::create([
            'author_id' => $course->teacher_id,
            'title' => 'Locked lesson',
            'slug' => 'locked-lesson',
            'long_description' => 'This lesson requires enrollment.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $course->curriculumItems()->create([
            'course_content_post_id' => $post->id,
            'position' => 1,
            'is_preview' => false,
        ]);

        $this->get(route('courses.content.show', [$course, $post]))
            ->assertRedirect(route('login', ['continue' => route('courses.show', $course, false)]));

        $this->get(route('login', ['continue' => route('courses.show', $course, false)]))
            ->assertOk()
            ->assertSessionHas('url.intended', route('courses.show', $course, false));
    }
}
