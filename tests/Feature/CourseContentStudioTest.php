<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\CourseCurriculumItem;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_course_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.course-categories.store'), [
            'name' => 'Spoken English',
            'description' => 'Speaking and pronunciation lessons.',
            'sort_order' => 5,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.course-categories.index'));

        $category = CourseCategory::firstOrFail();
        $this->assertSame('spoken-english', $category->slug);
        $this->assertTrue($category->is_visible);

        $this->actingAs($admin)->put(route('admin.course-categories.update', $category), [
            'name' => 'English Speaking',
            'description' => 'Updated category.',
            'sort_order' => 2,
        ])->assertRedirect(route('admin.course-categories.index'));

        $this->assertDatabaseHas('course_categories', [
            'id' => $category->id,
            'name' => 'English Speaking',
            'is_visible' => 0,
        ]);

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category->fresh()))
            ->assertRedirect();
        $this->assertDatabaseMissing('course_categories', ['id' => $category->id]);
    }

    public function test_category_cannot_be_deleted_while_content_posts_use_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar']);
        $post = $this->makePost($admin, $category, 'Grammar foundations');

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category))
            ->assertStatus(422);

        $this->assertDatabaseHas('course_categories', ['id' => $category->id]);
        $this->assertTrue($post->fresh()->categories->contains($category->id));
    }

    public function test_admin_can_create_content_posts_with_multiple_categories_tags_and_safe_video_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $grammar = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar']);
        $speaking = CourseCategory::create(['name' => 'Speaking', 'slug' => 'speaking']);

        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'Present Perfect',
            'short_description' => 'A practical tense lesson.',
            'long_description' => 'Learn how to use the present perfect tense.',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'tags' => 'grammar, speaking, grammar',
            'status' => 'published',
            'category_ids' => [$grammar->id, $speaking->id],
        ])->assertRedirect(route('admin.course-content-posts.index'));

        $post = CourseContentPost::firstOrFail();
        $this->assertSame('present-perfect', $post->slug);
        $this->assertSame(['grammar', 'speaking'], $post->tags);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $post->video_embed_url);
        $this->assertSame(2, $post->categories()->count());
        $this->assertNotNull($post->published_at);
    }

    public function test_content_post_rejects_unsupported_video_hosts_and_http_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.course-content-posts.create'))
            ->post(route('admin.course-content-posts.store'), [
                'title' => 'Unsafe embed',
                'long_description' => 'Test content',
                'video_url' => 'https://example.com/video/123',
                'status' => 'draft',
            ])->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->from(route('admin.course-content-posts.create'))
            ->post(route('admin.course-content-posts.store'), [
                'title' => 'Insecure embed',
                'long_description' => 'Test content',
                'video_url' => 'http://youtu.be/dQw4w9WgXcQ',
                'status' => 'draft',
            ])->assertSessionHasErrors('video_url');

        $this->assertDatabaseCount('course_content_posts', 0);
    }

    public function test_admin_can_assemble_and_reorder_published_posts_in_course_curriculum(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $first = $this->makePost($admin, null, 'First lesson');
        $second = $this->makePost($admin, null, 'Second lesson');

        $this->actingAs($admin)->put(route('admin.course-builder.update', $course), [
            'post_ids' => [$first->id, $second->id],
            'section_titles' => [$first->id => 'Module A', $second->id => 'Module A'],
            'positions' => [$first->id => 20, $second->id => 10],
            'previews' => [$first->id => '1'],
            'durations' => [$first->id => 15, $second->id => 25],
        ])->assertRedirect(route('admin.course-builder.edit', $course));

        $this->assertSame(
            [$second->id, $first->id],
            $course->curriculumItems()->orderBy('position')->pluck('course_content_post_id')->all()
        );
        $this->assertDatabaseHas('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $first->id,
            'section_title' => 'Module A',
            'is_preview' => 1,
            'duration_minutes' => 15,
        ]);

        $this->actingAs($admin)->put(route('admin.course-builder.update', $course), [
            'post_ids' => [$second->id],
            'positions' => [$second->id => 5],
        ])->assertRedirect();

        $this->assertDatabaseMissing('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $first->id,
        ]);
        $this->assertSame(1, $course->curriculumItems()->count());
    }

    public function test_preview_content_is_public_but_locked_curriculum_requires_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $preview = $this->makePost($admin, null, 'Free preview');
        $locked = $this->makePost($admin, null, 'Members only lesson');

        CourseCurriculumItem::create([
            'course_id' => $course->id, 'course_content_post_id' => $preview->id,
            'position' => 10, 'is_preview' => true,
        ]);
        CourseCurriculumItem::create([
            'course_id' => $course->id, 'course_content_post_id' => $locked->id,
            'position' => 20, 'is_preview' => false,
        ]);

        $this->get(route('courses.content.show', [$course, $preview]))->assertOk()->assertSee('Free preview');
        $this->get(route('courses.content.show', [$course, $locked]))
            ->assertRedirect(route('login', ['continue' => route('courses.show', $course, false)]));

        $this->get(route('login', ['continue' => route('courses.show', $course, false)]))
            ->assertOk()
            ->assertSessionHas('url.intended', route('courses.show', $course, false));

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $this->actingAs($student)->get(route('courses.content.show', [$course, $locked]))
            ->assertOk()->assertSee('Members only lesson');
    }

    public function test_non_admin_cannot_access_content_studio_or_course_builder(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($student)->get(route('admin.course-categories.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.course-content-posts.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.course-builder.edit', $course))->assertForbidden();
    }

    private function makePost(User $author, ?CourseCategory $category, string $title): CourseContentPost
    {
        $post = CourseContentPost::create([
            'author_id' => $author->id,
            'title' => $title,
            'slug' => str()->slug($title),
            'short_description' => 'Short description',
            'long_description' => 'Lesson content',
            'status' => 'published',
            'published_at' => now(),
            'tags' => ['test'],
        ]);

        if ($category) {
            $post->categories()->attach($category->id);
        }

        return $post;
    }
}
