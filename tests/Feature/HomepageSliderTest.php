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
        ])->all();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), ['home_slides' => $slides])
            ->assertRedirect(route('admin.settings.index'));

        $saved = json_decode((string) Setting::value('home_slides'), true);
        $this->assertCount(8, $saved);
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

    public function test_admin_saves_mobile_art_schedule_order_and_slide_presentation_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $desktop = MediaAsset::create(['uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/desktop.jpg', 'original_name' => 'desktop.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000]);
        $mobile = MediaAsset::create(['uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/mobile.jpg', 'original_name' => 'mobile.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000]);

        $this->actingAs($admin)->post(route('admin.settings.update'), ['home_slides' => [[
            'enabled' => '1', 'image_id' => (string) $desktop->id, 'mobile_image_id' => (string) $mobile->id,
            'title' => 'Responsive slide', 'sort_order' => '4', 'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'), 'secondary_button_label' => 'Student login',
            'secondary_button_url' => '/login', 'image_position_x' => '30', 'image_position_y' => '40',
            'mobile_position_x' => '70', 'mobile_position_y' => '20', 'content_alignment' => 'center',
            'overlay_color' => '#123456',
        ]]])->assertRedirect(route('admin.settings.index'));

        $saved = json_decode((string) Setting::value('home_slides'), true);
        $this->assertSame($mobile->id, $saved[0]['mobile_image_id']);
        $this->assertSame(4, $saved[0]['sort_order']);
        $this->assertSame('Student login', $saved[0]['secondary_button_label']);
        $this->assertSame('/login', $saved[0]['secondary_button_url']);
        $this->assertSame(30, $saved[0]['image_position_x']);
        $this->assertSame(70, $saved[0]['mobile_position_x']);
        $this->assertSame('center', $saved[0]['content_alignment']);
        $this->assertSame('#123456', $saved[0]['overlay_color']);
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
