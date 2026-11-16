<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSliderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_three_enabled_homepage_slides_from_media_library(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assets = collect(range(1, 3))->map(fn ($number) => MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => "media/slide-{$number}.jpg",
            'original_name' => "slide-{$number}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ]));

        $slides = $assets->map(fn ($asset, $index) => [
            'enabled' => '1',
            'image_id' => (string) $asset->id,
            'title' => 'Slide '.($index + 1),
            'subtitle' => 'Homepage message '.($index + 1),
            'button_label' => 'Explore',
            'button_url' => '/courses',
        ])->all();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), ['home_slides' => $slides])
            ->assertRedirect(route('admin.settings.index'));

        $saved = json_decode((string) Setting::value('home_slides'), true);
        $this->assertCount(3, $saved);
        $this->assertSame('Slide 1', $saved[0]['title']);
        $this->assertSame('/courses', $saved[0]['button_url']);
    }

    public function test_public_homepage_renders_enabled_slider_content_and_media(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/hero-slide.jpg',
            'original_name' => 'hero-slide.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
            'alt_text' => 'Students learning English',
        ]);

        Setting::setValue('home_slides', json_encode([[
            'enabled' => true,
            'image_id' => $asset->id,
            'title' => 'Learn English with confidence',
            'subtitle' => 'Join our next batch today.',
            'button_label' => 'Browse courses',
            'button_url' => '/courses',
        ]], JSON_THROW_ON_ERROR));

        $this->get('/')
            ->assertOk()
            ->assertSee('Learn English with confidence')
            ->assertSee('Join our next batch today.')
            ->assertSee('Browse courses')
            ->assertSee('Students learning English')
            ->assertSee('/storage/media/hero-slide.jpg', false);
    }

    public function test_slider_media_cannot_be_deleted_while_a_slide_uses_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/keep-this-slide.jpg',
            'original_name' => 'keep-this-slide.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ]);
        Setting::setValue('home_slides', json_encode([[
            'enabled' => true,
            'image_id' => $asset->id,
        ]], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->delete(route('admin.media.destroy', $asset))
            ->assertStatus(422);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_unsafe_slider_button_urls_are_not_saved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/safe.jpg',
            'original_name' => 'safe.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'home_slides' => [[
                'enabled' => '1',
                'image_id' => (string) $asset->id,
                'title' => 'Safe slide',
                'button_label' => 'Click',
                'button_url' => 'javascript:alert(1)',
            ]],
        ])->assertRedirect(route('admin.settings.index'));

        $saved = json_decode((string) Setting::value('home_slides'), true);
        $this->assertSame('', $saved[0]['button_url']);
    }
}
