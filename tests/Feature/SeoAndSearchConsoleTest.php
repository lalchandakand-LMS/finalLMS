<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_sitemap_does_not_include_admin_paths(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/admin');
    }
}
