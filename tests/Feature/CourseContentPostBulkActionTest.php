<?php

namespace Tests\Feature;

use App\Models\CourseContentPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentPostBulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_publish_archive_and_return_posts_to_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $draft = $this->makePost($admin, 'Bulk draft', 'draft');
        $archived = $this->makePost($admin, 'Bulk archived', 'archived', now()->subDays(5));
        $published = $this->makePost($admin, 'Bulk published', 'published', now()->subDays(2));

        $this->actingAs($admin)->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$draft->id, $archived->id, $published->id],
            'status' => 'published',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->published_at);
        $this->assertSame('published', $archived->fresh()->status);
        $this->assertEquals($archived->published_at->timestamp, $archived->fresh()->published_at->timestamp);
        $this->assertSame('published', $published->fresh()->status);

        $this->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$draft->id, $archived->id, $published->id],
            'status' => 'archived',
        ])->assertRedirect();

        $this->assertSame('archived', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->published_at);

        $this->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$draft->id],
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->published_at);
    }

    public function test_bulk_update_requires_unique_existing_post_ids_and_valid_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = $this->makePost($admin, 'Bulk validation', 'draft');

        $this->actingAs($admin)
            ->from(route('admin.course-content-posts.index'))
            ->post(route('admin.course-content-posts.bulk-update'), [
                'post_ids' => [],
                'status' => 'published',
            ])
            ->assertSessionHasErrors('post_ids');

        $this->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$post->id, $post->id],
            'status' => 'published',
        ])->assertSessionHasErrors('post_ids.1');

        $this->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$post->id],
            'status' => 'deleted',
        ])->assertSessionHasErrors('status');

        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_teacher_cannot_bulk_update_content_posts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $post = $this->makePost(User::factory()->create(['role' => 'admin']), 'Protected post', 'draft');

        $this->actingAs($teacher)->post(route('admin.course-content-posts.bulk-update'), [
            'post_ids' => [$post->id],
            'status' => 'published',
        ])->assertForbidden();

        $this->assertSame('draft', $post->fresh()->status);
    }

    private function makePost(User $admin, string $title, string $status, $publishedAt = null): CourseContentPost
    {
        return CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'long_description' => $title.' description',
            'status' => $status,
            'published_at' => $publishedAt,
            'tags' => [],
        ]);
    }
}
