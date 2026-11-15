<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndSearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_public_landing_and_catalog_routes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('landing'), false)
            ->assertSee(route('courses.index'), false)
            ->assertSee(route('news.index'), false);
    }

    public function test_robots_txt_points_to_sitemap_and_disallows_private_areas(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /dashboard')
            ->assertSee(url('/sitemap.xml'));
    }

    public function test_admin_can_save_google_search_console_verification_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'google_search_console_verification' => 'google-site-token_123',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', [
            'key' => 'google_search_console_verification',
            'value' => 'google-site-token_123',
        ]);

        Auth::logout();

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="google-site-token_123">', false);
    }

    public function test_search_console_verification_token_rejects_markup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'google_search_console_verification' => '<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors('google_search_console_verification');
    }

    public function test_student_registration_emits_meta_pixel_event_after_marketing_consent(): void
    {
        Notification::fake();
        Setting::setValue('facebook_pixel_id', '123456789012345');

        $payload = [
            'name' => 'Pixel Test Student',
            'email' => 'pixel-student@example.test',
            'phone_number' => '+8801712345678',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ];

        $this->withCookie('marketing_consent', 'accepted')
            ->post(route('register.store'), $payload)
            ->assertRedirect(route('login'));

        $this->withCookie('marketing_consent', 'accepted')
            ->get(route('login'))
            ->assertOk()
            ->assertSee("fbq('track','CompleteRegistration')", false);
    }

    public function test_meta_pixel_registration_event_is_not_rendered_without_marketing_consent(): void
    {
        Notification::fake();
        Setting::setValue('facebook_pixel_id', '123456789012345');

        $payload = [
            'name' => 'No Consent Student',
            'email' => 'no-consent-student@example.test',
            'phone_number' => '+8801812345678',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ];

        $this->withCookie('marketing_consent', 'declined')
            ->post(route('register.store'), $payload)
            ->assertRedirect(route('login'));

        $this->withCookie('marketing_consent', 'declined')
            ->get(route('login'))
            ->assertOk()
            ->assertDontSee("fbq('track','CompleteRegistration')", false)
            ->assertDontSee('connect.facebook.net', false);
    }

    public function test_admin_marketing_center_shows_tracking_and_search_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Setting::setValue('facebook_pixel_id', '123456789012345');
        Setting::setValue('google_analytics_id', 'G-ABC12345');

        $this->actingAs($admin)
            ->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('Marketing &amp; Boost Center', false)
            ->assertSee('Meta Pixel')
            ->assertSee('123456789012345')
            ->assertSee('G-ABC12345')
            ->assertSee(route('seo.sitemap'), false)
            ->assertSee('adsmanager.facebook.com', false);
    }

    public function test_sitemap_does_not_include_admin_paths(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/admin');
    }
}
