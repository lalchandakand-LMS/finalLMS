<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCategoryAndMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_course_category_and_assign_it_to_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($admin)->post(route('admin.course-categories.store'), [
            'name' => 'Spoken English',
            'description' => 'Speaking and listening courses',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $category = CourseCategory::where('name', 'Spoken English')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Everyday English',
            'short_description' => 'Build everyday speaking confidence.',
            'description' => 'A complete course for practical English communication.',
            'teacher_id' => $teacher->id,
            'price' => 2500,
            'status' => 'draft',
            'category_ids' => [$category->id],
            'tags' => 'speaking, grammar, vocabulary, speaking',
            'seo_title' => 'Everyday English Course',
            'seo_description' => 'Learn practical everyday English.',
            'learning_outcomes' => "Speak with confidence\nImprove listening",
            'requirements' => 'Basic literacy',
            'level' => 'Beginner',
            'language' => 'Bangla & English',
            'duration_minutes' => 720,
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::where('title', 'Everyday English')->firstOrFail();
        $this->assertTrue($course->categories()->whereKey($category->id)->exists());
        $this->assertSame(['speaking', 'grammar', 'vocabulary'], $course->tags);
        $this->assertSame('Everyday English Course', $course->seo_title);
    }

    public function test_content_post_uses_admin_managed_course_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Present Simple Tense',
            'excerpt' => 'A short grammar guide',
            'body' => 'Full learning content for students.',
            'category_ids' => [$category->id],
            'tags' => 'grammar, tense',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => 'draft',
            'seo_title' => 'Present Simple Tense',
            'seo_description' => 'Understand the present simple tense.',
        ])->assertRedirect(route('admin.news.index'));

        $post = BlogPost::where('title', 'Present Simple Tense')->firstOrFail();
        $this->assertTrue($post->categories()->whereKey($category->id)->exists());
        $this->assertSame('Grammar', $post->category);
    }

    public function test_non_teacher_account_cannot_be_assigned_as_course_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Invalid Teacher Assignment',
            'description' => 'This course should not be saved.',
            'teacher_id' => $student->id,
            'price' => 100,
            'status' => 'draft',
        ])->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseMissing('courses', ['title' => 'Invalid Teacher Assignment']);
    }

    public function test_course_with_enrollment_history_is_archived_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Historical Course',
            'slug' => 'historical-course',
            'description' => 'Course with enrollment history.',
            'price' => 1500,
            'status' => 'published',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'status' => 'archived']);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_course_slug_is_made_unique_when_titles_are_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $payload = [
            'title' => 'Same Course Title',
            'description' => 'Course description.',
            'teacher_id' => $teacher->id,
            'price' => 100,
            'status' => 'draft',
        ];

        $this->actingAs($admin)->post(route('admin.courses.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.courses.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('courses', ['slug' => 'same-course-title']);
        $this->assertDatabaseHas('courses', ['slug' => 'same-course-title-2']);
    }
    public function test_public_catalog_filters_by_managed_category_and_only_shows_published_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $grammar = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar', 'is_active' => true]);
        $speaking = CourseCategory::create(['name' => 'Speaking', 'slug' => 'speaking', 'is_active' => true]);

        $grammarCourse = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Grammar Foundation',
            'slug' => 'grammar-foundation',
            'short_description' => 'Build grammar skills.',
            'description' => 'Grammar course.',
            'price' => 1000,
            'status' => 'published',
            'level' => 'Beginner',
            'language' => 'English',
            'tags' => ['grammar'],
        ]);
        $grammarCourse->categories()->attach($grammar->id);

        $speakingCourse = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Speaking Practice',
            'slug' => 'speaking-practice',
            'description' => 'Speaking course.',
            'price' => 2000,
            'status' => 'published',
        ]);
        $speakingCourse->categories()->attach($speaking->id);

        Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Hidden Draft',
            'slug' => 'hidden-draft',
            'description' => 'Draft course.',
            'price' => 0,
            'status' => 'draft',
        ]);

        $this->get(route('courses.index', ['category' => 'grammar', 'level' => 'Beginner']))
            ->assertOk()
            ->assertSee('Grammar Foundation')
            ->assertDontSee('Speaking Practice')
            ->assertDontSee('Hidden Draft');
    }

    public function test_deleting_category_detaches_links_without_deleting_courses_or_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = CourseCategory::create(['name' => 'General English', 'slug' => 'general-english', 'is_active' => true]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'General Course',
            'slug' => 'general-course',
            'description' => 'Keep this course.',
            'price' => 500,
            'status' => 'draft',
        ]);
        $course->categories()->attach($category->id);
        $post = BlogPost::create([
            'author_id' => $admin->id,
            'title' => 'General Lesson',
            'slug' => 'general-lesson',
            'body' => 'Keep this post.',
            'status' => 'draft',
        ]);
        $post->categories()->attach($category->id);

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('course_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('course_category_course', ['course_id' => $course->id, 'course_category_id' => $category->id]);
        $this->assertDatabaseMissing('blog_post_course_category', ['blog_post_id' => $post->id, 'course_category_id' => $category->id]);
    }

}
