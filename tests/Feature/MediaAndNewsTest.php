<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAndNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_delete_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('classroom.jpg')],
            'alt_text' => 'Classroom',
        ])->assertRedirect();

        $asset = MediaAsset::firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertRedirect();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_public_news_only_shows_published_articles(): void
    {
        $author = User::factory()->create(['role' => 'admin']);
        BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Published update',
            'slug' => 'published-update',
            'excerpt' => 'A public announcement',
            'body' => 'Full announcement body',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        BlogPost::create([
            'author_id' => $author->id,
            'title' => 'Private draft',
            'slug' => 'private-draft',
            'body' => 'Draft content',
            'status' => 'draft',
        ]);

        $this->get(route('news.index'))->assertOk()->assertSee('Published update')->assertDontSee('Private draft');
        $this->get(route('news.show', 'published-update'))->assertOk()->assertSee('Full announcement body');
        $this->get(route('news.show', 'private-draft'))->assertNotFound();
    }

    public function test_only_admin_can_access_media_and_news_management(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.news.index'))->assertForbidden();
    }

    public function test_blog_cover_must_be_an_image_asset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $document = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/handbook.pdf',
            'original_name' => 'handbook.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Article with invalid cover',
            'body' => 'Article content.',
            'status' => 'draft',
            'cover_media_id' => $document->id,
        ])->assertSessionHasErrors('cover_media_id');

        $this->assertDatabaseCount('blog_posts', 0);
    }

}
