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

    public function test_admin_can_save_eight_enabled_homepage_slides_from_media_library(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assets = collect(range(1, 8))->map(fn ($number) => MediaAsset::create([
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
            'sort_order' => $index,
            'mobile_image_id' => $index === 0 ? $assets[1]->id : null,
            'starts_at' => $index === 0 ? now()->subHour()->format('Y-m-d H:i:s') : null,
            'ends_at' => $index === 0 ? now()->addDay()->format('Y-m-d H:i:s') : null,
        ])->all();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), ['home_slides' => $slides])
            ->assertRedirect(route('admin.settings.index'));

        $saved = json_decode((string) Setting::value('home_slides'), true);
        $this->assertCount(8, $saved);
        $this->assertSame('Slide 1', $saved[0]['title']);
        $this->assertSame('/courses', $saved[0]['button_url']);
        $this->assertSame($assets[1]->id, $saved[0]['mobile_image_id']);
        $this->assertSame(0, $saved[0]['sort_order']);
        $this->assertNotEmpty($saved[0]['starts_at']);
        $this->assertNotEmpty($saved[0]['ends_at']);
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
            ->assertSee('Online Course')
            ->assertSee('Available online batches')
            ->assertSee('Book your seat now!')
            ->assertSee('See All Courses')
            ->assertSee('/storage/media/hero-slide.jpg', false);
    }

    public function test_public_homepage_uses_mobile_slide_art_and_respects_publication_window_and_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $desktop = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/desktop.jpg',
            'original_name' => 'desktop.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);
        $mobile = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/mobile.jpg',
            'original_name' => 'mobile.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);
        $ordered = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/ordered.jpg',
            'original_name' => 'ordered.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);
        $hidden = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/future.jpg',
            'original_name' => 'future.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);

        Setting::setValue('home_slides', json_encode([
            ['enabled' => true, 'image_id' => $desktop->id, 'mobile_image_id' => $mobile->id, 'title' => 'Responsive hero', 'sort_order' => 2],
            ['enabled' => true, 'image_id' => $ordered->id, 'title' => 'Earlier order', 'sort_order' => 1],
            ['enabled' => true, 'image_id' => $hidden->id, 'title' => 'Not yet published', 'sort_order' => 0, 'starts_at' => now()->addDay()->format('Y-m-d H:i:s')],
        ], JSON_THROW_ON_ERROR));

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Earlier order', 'Responsive hero'])
            ->assertSee('/storage/media/mobile.jpg', false)
            ->assertDontSee('Not yet published');
    }

    public function test_settings_reject_non_image_mobile_slide_assets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $image = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/desktop-safe.jpg',
            'original_name' => 'desktop-safe.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);
        $document = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/handbook.pdf',
            'original_name' => 'handbook.pdf', 'mime_type' => 'application/pdf', 'size' => 1000,
        ]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'home_slides' => [[
                'enabled' => '1', 'image_id' => $image->id, 'mobile_image_id' => $document->id,
            ]],
        ])->assertSessionHasErrors('home_slides.0.mobile_image_id');
    }

    public function test_unrelated_settings_updates_preserve_existing_homepage_slides(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $savedSlides = [['enabled' => true, 'image_id' => 987, 'title' => 'Keep this slide']];
        Setting::setValue('home_slides', json_encode($savedSlides, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'institution_name' => 'Alom English Center',
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertSame($savedSlides, json_decode((string) Setting::value('home_slides'), true));
    }

    public function test_mobile_slide_assets_cannot_be_deleted_while_in_use(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $desktop = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/desktop-keep.jpg',
            'original_name' => 'desktop-keep.jpg', 'mime_type' => 'image/jpeg', 'size' => 100,
        ]);
        $mobile = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/mobile-keep.jpg',
            'original_name' => 'mobile-keep.jpg', 'mime_type' => 'image/jpeg', 'size' => 100,
        ]);
        Setting::setValue('home_slides', json_encode([[
            'enabled' => true, 'image_id' => $desktop->id, 'mobile_image_id' => $mobile->id,
        ]], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->delete(route('admin.media.destroy', $mobile))->assertStatus(422);
        $this->assertDatabaseHas('media_assets', ['id' => $mobile->id]);
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

    public function test_saved_draft_slide_assets_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/draft-slide.jpg',
            'original_name' => 'draft-slide.jpg', 'mime_type' => 'image/jpeg', 'size' => 100,
        ]);
        Setting::setValue('homepage_draft', json_encode([
            'home_slides' => [['enabled' => true, 'image_id' => $asset->id]],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertStatus(422);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_loading_screen_logo_cannot_be_deleted_while_in_use(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/loader-logo.png',
            'original_name' => 'loader-logo.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);
        Setting::setValue('loading_screen_logo_media_id', (string) $asset->id);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertStatus(422);
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
