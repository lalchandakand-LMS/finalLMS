<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meta_ads_connection_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.marketing.meta-ads.index'))
            ->assertOk()
            ->assertSee('Facebook & Instagram Ads');
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
            ->post(route('admin.marketing.meta-ads.campaigns.pause', '111111111111112'), ['account_id' => '99999'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/111111111111112'));
    }
    public function test_campaign_cannot_be_paused_through_a_different_ad_account(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345']]]),
            'https://graph.facebook.com/v27.0/111111111111111*' => Http::response(['id' => '111111111111111', 'account_id' => '54321']),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.pause', '111111111111111'), ['account_id' => '12345'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/111111111111111'));
    }

    public function test_resuming_a_paused_campaign_requires_matching_account_and_sends_active_status(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345']]]),
            'https://graph.facebook.com/v27.0/111111111111113*' => Http::response(['id' => '111111111111113', 'account_id' => '12345', 'status' => 'PAUSED']),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.resume', '111111111111113'), ['account_id' => '12345'])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/111111111111113')
            && str_contains($request->body(), 'status=ACTIVE'));
    }


    public function test_admin_can_create_a_complete_paused_meta_traffic_campaign_with_bdt_budget(): void
    {
        config(['services.meta_ads.app_id' => 'test-app']);
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/me/adaccounts')) {
                return Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345', 'currency' => 'BDT']]], 200);
            }
            if (str_contains($url, '/me/accounts')) {
                return Http::response(['data' => [['id' => '98765', 'name' => 'LMS Page', 'tasks' => ['ADVERTISE']]]], 200);
            }
            if (str_ends_with($url, '/act_12345/campaigns')) {
                return Http::response(['id' => '111111111111111'], 200);
            }
            if (str_ends_with($url, '/act_12345/adsets')) {
                return Http::response(['id' => '222222222222222'], 200);
            }
            if (str_ends_with($url, '/act_12345/adcreatives')) {
                return Http::response(['id' => '333333333333333'], 200);
            }
            if (str_ends_with($url, '/act_12345/ads')) {
                return Http::response(['id' => '444444444444444'], 200);
            }

            return Http::response(['error' => ['message' => 'Unexpected request']], 500);
        });
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.store'), [
                'account_id' => '12345',
                'page_id' => '98765',
                'name' => 'LMS HSC English',
                'message' => 'Enroll in our English course today.',
                'destination_url' => 'https://lms.example/courses/english',
                'image_url' => 'https://lms.example/images/english.jpg',
                'daily_budget_bdt' => 300,
                'age_min' => 18,
                'age_max' => 45,
            ])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meta_boost_campaigns', [
            'title' => 'LMS HSC English',
            'meta_campaign_id' => '111111111111111',
            'meta_adset_id' => '222222222222222',
            'meta_creative_id' => '333333333333333',
            'meta_ad_id' => '444444444444444',
            'meta_page_id' => '98765',
            'status' => 'paused',
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/campaigns') || str_contains($url, '/adsets') || str_contains($url, '/ads')) {
                return $request->method() === 'POST' && $request['status'] === 'PAUSED';
            }

            return true;
        });
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/act_12345/adsets')
            && (int) $request['daily_budget'] === 30000);
    }

    public function test_meta_campaign_creation_rejects_non_bdt_ad_accounts_before_creating_anything(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345', 'currency' => 'USD']]]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->post(route('admin.marketing.meta-ads.campaigns.store'), [
                'account_id' => '12345',
                'page_id' => '98765',
                'name' => 'LMS campaign',
                'message' => 'Course enrollment is open.',
                'destination_url' => 'https://lms.example/course',
                'image_url' => 'https://lms.example/course.jpg',
                'daily_budget_bdt' => 300,
                'age_min' => 18,
                'age_max' => 45,
            ])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/campaigns'));
        $this->assertDatabaseCount('meta_boost_campaigns', 0);
    }

    public function test_meta_campaign_creation_rejects_a_page_not_accessible_to_connected_user(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345', 'currency' => 'BDT']]]),
            'https://graph.facebook.com/v27.0/me/accounts*' => Http::response(['data' => [['id' => '11111', 'name' => 'Other Page']]]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->post(route('admin.marketing.meta-ads.campaigns.store'), [
                'account_id' => '12345',
                'page_id' => '98765',
                'name' => 'LMS campaign',
                'message' => 'Course enrollment is open.',
                'destination_url' => 'https://lms.example/course',
                'image_url' => 'https://lms.example/course.jpg',
                'daily_budget_bdt' => 300,
                'age_min' => 18,
                'age_max' => 45,
            ])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/campaigns'));
    }

    public function test_meta_campaign_creation_validates_age_range_and_budget(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.store'), [
                'account_id' => '12345',
                'page_id' => '98765',
                'name' => 'LMS campaign',
                'message' => 'Course enrollment is open.',
                'destination_url' => 'https://lms.example/course',
                'image_url' => 'https://lms.example/course.jpg',
                'daily_budget_bdt' => 50,
                'age_min' => 45,
                'age_max' => 18,
            ])
            ->assertSessionHasErrors(['daily_budget_bdt', 'age_max']);
    }



    public function test_resuming_lms_created_campaign_activates_ad_and_adset_before_campaign(): void
    {
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $local = \App\Models\MetaBoostCampaign::create([
            'title' => 'LMS campaign',
            'destination_url' => 'https://lms.example/course',
            'objective' => 'traffic',
            'daily_budget_bdt' => 300,
            'status' => 'paused',
            'meta_campaign_id' => '111111111111114',
            'meta_adset_id' => '222222222222224',
            'meta_creative_id' => '333333333333334',
            'meta_ad_id' => '444444444444444',
            'meta_page_id' => '98765',
            'created_by' => $admin->id,
        ]);
        Http::fake([
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345']]]),
            'https://graph.facebook.com/v27.0/111111111111114*' => Http::response(['id' => '111111111111114', 'account_id' => '12345', 'status' => 'PAUSED']),
            'https://graph.facebook.com/v27.0/444444444444444' => Http::response(['success' => true]),
            'https://graph.facebook.com/v27.0/222222222222224' => Http::response(['success' => true]),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.resume', '111111111111114'), ['account_id' => '12345'])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meta_boost_campaigns', ['id' => $local->id, 'status' => 'running']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/444444444444444') && $request['status'] === 'ACTIVE');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/222222222222224') && $request['status'] === 'ACTIVE');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/111111111111114') && $request['status'] === 'ACTIVE');
    }


    public function test_admin_can_create_a_paused_boost_from_a_published_lms_course(): void
    {
        config(['services.meta_ads.app_id' => 'test-app']);
        Setting::setValue('meta_ads_access_token', Crypt::encryptString('secret-token'));
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English Masterclass',
            'slug' => 'spoken-english-masterclass',
            'description' => 'Complete spoken English course.',
            'short_description' => 'Build confidence speaking English.',
            'price' => 1500,
            'status' => 'published',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/me/adaccounts')) return Http::response(['data' => [['id' => 'act_12345', 'account_id' => '12345', 'currency' => 'BDT']]]);
            if (str_contains($url, '/me/accounts')) return Http::response(['data' => [['id' => '98765', 'name' => 'LMS Page', 'tasks' => ['ADVERTISE']]]);
            if (str_ends_with($url, '/act_12345/campaigns')) return Http::response(['id' => '111111111111121']);
            if (str_ends_with($url, '/act_12345/adsets')) return Http::response(['id' => '222222222222221']);
            if (str_ends_with($url, '/act_12345/adcreatives')) return Http::response(['id' => '333333333333331']);
            if (str_ends_with($url, '/act_12345/ads')) return Http::response(['id' => '444444444444441']);

            return Http::response(['error' => ['message' => 'Unexpected request']], 500);
        });

        $this->actingAs($admin)
            ->post(route('admin.marketing.meta-ads.campaigns.store'), [
                'account_id' => '12345',
                'page_id' => '98765',
                'source_reference' => 'course:'.$course->id,
                'image_url' => 'https://lms.example/images/course.jpg',
                'daily_budget_bdt' => 300,
                'age_min' => 18,
                'age_max' => 45,
            ])
            ->assertRedirect(route('admin.marketing.meta-ads.index', ['account' => '12345']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('meta_boost_campaigns', [
            'title' => 'Spoken English Masterclass - Meta Boost',
            'destination_url' => route('courses.show', $course),
            'meta_campaign_id' => '111111111111121',
            'status' => 'paused',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/act_12345/adcreatives')
            && data_get(json_decode((string) $request['object_story_spec'], true), 'link_data.link') === route('courses.show', $course));
    }


}
