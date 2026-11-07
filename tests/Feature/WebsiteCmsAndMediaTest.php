<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteCmsAndMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_manage_media_library_assets(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'files' => [UploadedFile::fake()->image('banner.jpg')],
            'alt_text' => 'Center banner',
        ])->assertRedirect();

        $asset = MediaAsset::query()->firstOrFail();
        $this->assertSame($admin->id, $asset->uploaded_by);
        $this->assertSame('Center banner', $asset->alt_text);
        Storage::disk('public')->assertExists($asset->path);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertRedirect();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_media_used_as_blog_cover_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/cover.jpg',
            'original_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'alt_text' => 'Cover',
        ]);
        BlogPost::create([
            'author_id' => $admin->id,
            'cover_media_id' => $asset->id,
            'title' => 'News story',
            'slug' => 'news-story',
            'body' => 'Article body',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertStatus(422);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_public_blog_only_displays_published_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BlogPost::create([
            'author_id' => $admin->id,
            'title' => 'Published update',
            'slug' => 'published-update',
            'body' => '<script>alert(1)</script>Safe text',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        BlogPost::create([
            'author_id' => $admin->id,
            'title' => 'Draft update',
            'slug' => 'draft-update',
            'body' => 'Draft body',
            'status' => 'draft',
        ]);

        $this->get(route('news.index'))->assertOk()->assertSee('Published update')->assertDontSee('Draft update');
        $this->get(route('news.show', 'published-update'))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>Safe text');
        $this->get(route('news.show', 'draft-update'))->assertNotFound();
    }

    public function test_admin_can_save_homepage_sections_and_limit_them_to_twelve(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'sections' => [
                ['title' => 'Welcome', 'body' => 'Hello learners', 'button_label' => 'Browse courses', 'button_url' => 'https://example.com', 'enabled' => '1'],
            ],
        ])->assertRedirect(route('admin.website-builder.index'));

        $saved = json_decode((string) Setting::value('website_sections'), true);
        $this->assertSame('Welcome', $saved[0]['title']);
        $this->assertTrue($saved[0]['enabled']);

        $tooMany = array_fill(0, 13, ['title' => 'Section', 'enabled' => '1']);
        $this->post(route('admin.website-builder.update'), ['sections' => $tooMany])
            ->assertSessionHasErrors('sections');
    }

    public function test_non_admin_cannot_manage_media_or_website_builder(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.website-builder.index'))->assertForbidden();
    }
}
