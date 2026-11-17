<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminHomeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_homepage_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'home_title' => 'Future Skills Academy',
                'home_subtitle' => 'Learn the skills that open tomorrow.',
                'home_cta_primary' => 'Explore programs',
                'home_cta_secondary' => 'Book a demo',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', [
            'key' => 'home_title',
            'value' => 'Future Skills Academy',
        ]);
    }

    public function test_admin_can_save_institution_default_locale_and_timezone_without_overwriting_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('institution_name', 'My Learning Center');

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'institution_default_locale' => 'bn',
                'institution_timezone' => 'Asia/Dhaka',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', ['key' => 'institution_name', 'value' => 'My Learning Center']);
        $this->assertDatabaseHas('settings', ['key' => 'institution_default_locale', 'value' => 'bn']);
        $this->assertDatabaseHas('settings', ['key' => 'institution_timezone', 'value' => 'Asia/Dhaka']);
    }

    public function test_admin_cannot_save_unsupported_institution_locale_or_timezone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'institution_default_locale' => 'fr',
                'institution_timezone' => 'Mars/Phobos',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['institution_default_locale', 'institution_timezone']);
    }

    public function test_homepage_uses_admin_configured_values(): void
    {
        Setting::setValue('home_title', 'Future Skills Academy');
        Setting::setValue('home_subtitle', 'Learn the skills that open tomorrow.');
        Setting::setValue('home_cta_primary', 'Explore programs');
        Setting::setValue('home_cta_secondary', 'Book a demo');

        $response = $this->get('/');

        $response->assertOk()->assertSee('Future Skills Academy')->assertSee('Explore programs');
    }

    public function test_admin_can_configure_seo_and_consent_gated_marketing_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'seo_title' => 'Aloms English Courses',
                'seo_description' => 'Learn English online with Aloms.',
                'seo_keywords' => 'English, speaking, learning',
                'google_analytics_id' => 'G-ABC12345',
                'google_tag_manager_id' => 'GTM-ABC1234',
                'facebook_pixel_id' => '123456789012345',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', [
            'key' => 'google_analytics_id',
            'value' => 'G-ABC12345',
        ]);

        Auth::logout();

        $this->get('/')
            ->assertOk()
            ->assertSee('Learn English online with Aloms.')
            ->assertSee('Cookie preferences')
            ->assertDontSee('G-ABC12345');

        $this->withUnencryptedCookie('marketing_consent', 'accepted')
            ->get('/')
            ->assertSee('G-ABC12345')
            ->assertSee('GTM-ABC1234')
            ->assertSee('123456789012345')
            ->assertSee('facebook.net', false);
    }
}
