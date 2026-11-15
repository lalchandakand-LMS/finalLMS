<?php

namespace Tests\\Feature;

use App\\Models\\Setting;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\Http;
use Tests\\TestCase;

class MetaAdsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meta_ads_connection_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.marketing.meta-ads.index'))
            ->assertOk()
            ->assertSee('Facebook &amp; Instagram Ads');
    }

    public function test_non_admin_cannot_access_meta_ads_integration(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->get(route('admin.marketing.meta-ads.index'))
            ->assertForbidden();
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->withSession(['meta_ads_oauth_state' => 'expected-state'])
            ->get(route('admin.marketing.meta-ads.callback', ['state' => 'wrong', 'code' => 'test']))
            ->assertRedirect(route('admin.marketing.meta-ads.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('settings', ['key' => 'meta_ads_access_token']);
    }

    public function test_callback_encrypts_token_before_persisting(): void
    {
        config([
            'services.meta_ads.app_id' => 'app-id',
            'services.meta_ads.app_secret' => 'app-secret',
            'services.meta_ads.redirect_uri' => 'https://lms.example/admin/marketing/meta-ads/callback',
        ]);
        Http::fake([
            'https://graph.facebook.com/v27.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-token', 'expires_in' => 3600])
                ->push(['access_token' => 'long-lived-token', 'expires_in' => 5184000]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->withSession(['meta_ads_oauth_state' => 'valid-state'])
            ->get(route('admin.marketing.meta-ads.callback', ['state' => 'valid-state', 'code' => 'oauth-code']))
            ->assertRedirect(route('admin.marketing.meta-ads.index'))
            ->assertSessionHas('success');

        $stored = Setting::value('meta_ads_access_token');
        $this->assertNotSame('long-lived-token', $stored);
        $this->assertSame('long-lived-token', Crypt::decryptString($stored));
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'long-lived-token'));
    }

    public function test_campaign_pause_requires_an_accessible_ad_account(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345']]]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.pause', 'campaign-1'), ['account_id' => '99999'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/campaign-1'));
    }
}
