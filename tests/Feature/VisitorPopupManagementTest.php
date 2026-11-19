<?php

namespace Tests\Feature;

use Aloms\PopupAds\PopupAdEvent;
use Aloms\PopupAds\PopupCampaign;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\MediaAsset;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Modules\ModuleRegistry;
use App\Services\Plugins\PluginRuntime;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class VisitorPopupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function activatePopupAdsPlugin(bool $bootRuntime = true): PluginInstallation
    {
        $packageRelative = 'plugins/aec/popup-ads/1.0.0';
        $packageRoot = base_path($packageRelative);
        $manifest = json_decode((string) file_get_contents($packageRoot.'/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath($packageRoot), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $realRoot = rtrim((string) realpath($packageRoot), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($realRoot)));
            $hashes[$relative] = hash_file('sha256', $item->getRealPath());
        }

        config(['plugins.trusted_public_keys' => ['aec-release-2026' => base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES))]]);

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.popup-ads')->first()
            ?? new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.popup-ads',
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'type' => $manifest['type'],
            'status' => 'active',
            'manifest' => $manifest,
            'package_sha256' => hash('sha256', 'signed-popup-ads-1.0.0'),
            'signature_key_id' => 'aec-release-2026',
            'signature_verified_at' => now(),
            'package_path' => $packageRelative,
            'installed_at' => now(),
            'activated_at' => now(),
            'metadata' => [
                'granted_permissions' => ['popup-ads.manage'],
                'integrity_version' => 1,
                'installed_file_hashes' => $hashes,
            ],
        ])->save();

        $migration = require $packageRoot.'/migrations/2026_11_15_200000_upgrade_popup_ads_plugin_tables.php';
        $migration->up();

        if ($bootRuntime) {
            $runtime = app(PluginRuntime::class);
            $runtime->registerActivePlugins();
            $runtime->bootActivePlugins();
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        return $plugin;
    }

    public function test_popup_ads_module_is_managed_by_plugin_manager_and_inactive_by_default(): void
    {
        $catalog = app(ModuleRegistry::class)->catalog();

        $this->assertArrayHasKey('popup_ads', $catalog);
        $this->assertTrue($catalog['popup_ads']['managed_by_plugin_manager']);
        $this->assertFalse($catalog['popup_ads']['installed']);
        $this->assertFalse($catalog['popup_ads']['enabled']);
        $this->assertSame('not_installed', $catalog['popup_ads']['status']);

        $this->expectException(\InvalidArgumentException::class);
        app(ModuleRegistry::class)->setEnabled('popup_ads', true);
    }

    public function test_absent_or_inactive_plugin_stops_serving_popup_routes_and_preserves_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $id = DB::table('visitor_popups')->insertGetId([
            'title' => 'Retained campaign',
            'body' => 'Should stay stored when plugin is inactive.',
            'popup_type' => 'offer',
            'audience' => 'all',
            'frequency' => 'once_per_session',
            'delay_seconds' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/admin/visitor-popups')->assertNotFound();
        $this->get('/popups/active')->assertNotFound();
        auth()->logout();
        $this->get(route('landing'))->assertOk()->assertDontSee('Retained campaign');

        $this->activatePopupAdsPlugin();
        $this->actingAs($admin)->get('/admin/visitor-popups')->assertOk()->assertSee('Retained campaign');

        PluginInstallation::query()->where('plugin_key', 'aec.popup-ads')->update(['status' => 'inactive']);

        $this->actingAs($admin)->get('/admin/visitor-popups')->assertNotFound();
        $this->get('/popups/active')->assertNotFound();
        auth()->logout();
        $this->get(route('landing'))->assertOk()->assertDontSee('Retained campaign');
        $this->assertDatabaseHas('visitor_popups', ['id' => $id, 'title' => 'Retained campaign']);
    }

    public function test_admin_can_crud_duplicate_pause_resume_archive_and_audit_campaigns(): void
    {
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.visitor-popups.store'), [
            'title' => 'Eid Grammar Offer',
            'body' => 'Enroll before Friday.',
            'popup_type' => 'offer',
            'content_mode' => 'mixed',
            'layout' => 'corner_card',
            'placement' => 'bottom_right',
            'max_width_px' => 420,
            'background_color' => '#ffffff',
            'text_color' => '#14213d',
            'accent_color' => '#1546a8',
            'button_label' => 'Enroll Now',
            'button_url' => '/register',
            'audience' => 'guests',
            'frequency' => 'once_per_day',
            'delay_seconds' => 2,
            'scroll_trigger_percent' => 25,
            'cooldown_hours' => 12,
            'status' => 'active',
            'priority' => 90,
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $popup = PopupCampaign::query()->firstOrFail();
        $this->assertTrue($popup->is_active);
        $this->assertSame('corner_card', $popup->layout);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.popup-ads.created',
        ]);

        $this->actingAs($admin)->get(route('admin.visitor-popups.edit', $popup->id))
            ->assertOk()
            ->assertSee('Eid Grammar Offer');

        $this->actingAs($admin)->put(route('admin.visitor-popups.update', $popup->id), [
            'title' => 'Eid Grammar Offer Updated',
            'body' => 'Enroll before Sunday.',
            'popup_type' => 'offer',
            'content_mode' => 'mixed',
            'layout' => 'full_banner',
            'placement' => 'bottom',
            'audience' => 'guests',
            'frequency' => 'once_per_day',
            'delay_seconds' => 3,
            'status' => 'active',
            'priority' => 95,
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $this->assertSame('Eid Grammar Offer Updated', $popup->fresh()->title);
        $this->assertSame('full_banner', $popup->fresh()->layout);

        $this->actingAs($admin)->post(route('admin.visitor-popups.duplicate', $popup->id))->assertRedirect();
        $this->assertDatabaseCount('visitor_popups', 2);

        $this->actingAs($admin)->patch(route('admin.visitor-popups.pause', $popup->id))->assertRedirect();
        $this->assertFalse($popup->fresh()->is_active);
        $this->assertSame('paused', $popup->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.visitor-popups.resume', $popup->id))->assertRedirect();
        $this->assertTrue($popup->fresh()->is_active);
        $this->assertSame('active', $popup->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.visitor-popups.archive', $popup->id))->assertRedirect();
        $this->assertFalse($popup->fresh()->is_active);
        $this->assertSame('archived', $popup->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.visitor-popups.destroy', $popup->id))->assertRedirect();
        $this->assertDatabaseMissing('visitor_popups', ['id' => $popup->id]);
    }

    public function test_rich_html_sanitization_and_unsafe_url_rejection_prevent_xss(): void
    {
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->from(route('admin.visitor-popups.create'))
            ->post(route('admin.visitor-popups.store'), [
                'title' => 'Unsafe CTA',
                'popup_type' => 'ad',
                'audience' => 'all',
                'frequency' => 'every_page',
                'delay_seconds' => 0,
                'button_url' => 'javascript:alert(1)',
            ])->assertSessionHasErrors('button_url');

        $this->actingAs($admin)->from(route('admin.visitor-popups.create'))
            ->post(route('admin.visitor-popups.store'), [
                'title' => 'Protocol relative',
                'popup_type' => 'ad',
                'audience' => 'all',
                'frequency' => 'every_page',
                'delay_seconds' => 0,
                'button_url' => '//evil.example/phish',
            ])->assertSessionHasErrors('button_url');

        $this->actingAs($admin)->post(route('admin.visitor-popups.store'), [
            'title' => 'Sanitized Announcement',
            'popup_type' => 'announcement',
            'content_mode' => 'sanitized_html',
            'rich_html' => '<p onclick="alert(1)"><strong>Big Update</strong> <script>alert("xss")</script><a href="javascript:alert(2)">Bad</a> <a href="https://example.com/offer">Good</a></p>',
            'audience' => 'all',
            'frequency' => 'once_per_session',
            'delay_seconds' => 0,
            'status' => 'active',
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $popup = PopupCampaign::query()->firstOrFail();
        $this->assertStringNotContainsString('<script>', (string) $popup->rich_html);
        $this->assertStringNotContainsString('onclick', (string) $popup->rich_html);
        $this->assertStringNotContainsString('javascript:', (string) $popup->rich_html);
        $this->assertStringContainsString('<strong>Big Update</strong>', (string) $popup->rich_html);
        $this->assertStringContainsString('https://example.com/offer', (string) $popup->rich_html);
        $this->assertStringContainsString('noopener noreferrer', (string) $popup->rich_html);

        auth()->logout();
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Sanitized Announcement')
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_image_uploads_validate_mime_and_store_through_media_assets(): void
    {
        Storage::fake('public');
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->from(route('admin.visitor-popups.create'))
            ->post(route('admin.visitor-popups.store'), [
                'title' => 'Reject SVG script',
                'popup_type' => 'banner',
                'audience' => 'all',
                'frequency' => 'once_per_session',
                'delay_seconds' => 0,
                'desktop_image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
            ])->assertSessionHasErrors('desktop_image');

        $this->actingAs($admin)->post(route('admin.visitor-popups.store'), [
            'title' => 'Responsive Banner',
            'popup_type' => 'banner',
            'content_mode' => 'image_only',
            'audience' => 'all',
            'frequency' => 'once_per_session',
            'delay_seconds' => 0,
            'status' => 'active',
            'desktop_image' => UploadedFile::fake()->image('desktop.png', 1200, 500),
            'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 600, 600),
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $popup = PopupCampaign::query()->with(['image', 'mobileImage'])->firstOrFail();
        $this->assertNotNull($popup->image_media_id);
        $this->assertNotNull($popup->mobile_image_media_id);
        $this->assertSame(2, MediaAsset::query()->count());
        Storage::disk('public')->assertExists($popup->image->path);
        Storage::disk('public')->assertExists($popup->mobileImage->path);
    }

    public function test_targeting_scheduling_priority_and_admin_preview_behave_accurately(): void
    {
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $branch = Branch::create(['name' => 'Dhaka Main', 'slug' => 'dhaka-main', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $studentInBranch = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id, 'email_verified_at' => now()]);
        $otherStudent = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Enrollment::create(['user_id' => $studentInBranch->id, 'course_id' => $course->id, 'status' => 'active']);

        $draftPopup = PopupCampaign::create([
            'title' => 'Secret Admin Draft',
            'body' => 'Should never leak publicly.',
            'popup_type' => 'announcement',
            'audience' => 'all',
            'frequency' => 'every_page',
            'delay_seconds' => 0,
            'is_active' => false,
            'status' => 'draft',
            'priority' => 999,
        ]);

        $targetedPopup = PopupCampaign::create([
            'title' => 'Branch Course Offer',
            'body' => 'Special workshop for enrolled Dhaka branch students.',
            'popup_type' => 'offer',
            'audience' => 'members',
            'target_paths' => "/courses*\n!/courses/excluded",
            'target_roles' => ['student'],
            'target_branch_id' => $branch->id,
            'target_course_id' => $course->id,
            'frequency' => 'once_per_day',
            'delay_seconds' => 0,
            'is_active' => true,
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'priority' => 50,
        ]);

        // Draft must never appear publicly, but admin preview works and non-admin is forbidden.
        $this->getJson('/popups/active')->assertOk()->assertJson(['popup' => null]);
        $this->actingAs($admin)->get(route('admin.visitor-popups.preview', $draftPopup->id))
            ->assertOk()
            ->assertSee('Secret Admin Draft')
            ->assertSee('Admin-only preview');
        $this->actingAs($studentInBranch)->get(route('admin.visitor-popups.preview', $draftPopup->id))->assertForbidden();

        // Public API callers cannot forge page/course/batch context to reveal a targeted campaign.
        $this->actingAs($studentInBranch)
            ->getJson('/popups/active?path=/courses&course_id='.$course->id.'&batch_id=999999')
            ->assertOk()
            ->assertJson(['popup' => null]);
        $this->actingAs($otherStudent)
            ->getJson('/popups/active?path=/courses&course_id='.$course->id)
            ->assertOk()
            ->assertJson(['popup' => null]);

        // The real course page uses server-verified route/enrollment/path context and can render it.
        $this->actingAs($studentInBranch)->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Branch Course Offer');
        $this->actingAs($studentInBranch)->get('/courses/excluded')->assertNotFound();
    }

    public function test_consent_aware_analytics_impression_caps_export_purge_and_access_control(): void
    {
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $popup = PopupCampaign::create([
            'title' => 'Analytics Cap Campaign',
            'body' => 'Limited impressions offer.',
            'popup_type' => 'ad',
            'audience' => 'all',
            'frequency' => 'every_page',
            'delay_seconds' => 0,
            'max_impressions' => 1,
            'analytics_enabled' => true,
            'require_consent_for_analytics' => true,
            'is_active' => true,
            'status' => 'active',
            'priority' => 10,
        ]);

        // Unsigned callers cannot forge campaign IDs to manipulate aggregate analytics.
        $this->postJson(route('popups.events', $popup->id), ['event' => 'impression'])->assertForbidden();
        $this->assertSame(0, PopupAdEvent::query()->count());

        $eventUrl = URL::temporarySignedRoute('popups.events', now()->addMinutes(5), ['popup' => $popup->id]);
        $expiredEventUrl = URL::temporarySignedRoute('popups.events', now()->subMinute(), ['popup' => $popup->id]);

        // Signatures bind the campaign path and query string and expire.
        $this->postJson($eventUrl.'&tampered=1', ['event' => 'impression'])->assertForbidden();
        $this->postJson($expiredEventUrl, ['event' => 'impression'])->assertForbidden();
        $tamperedPathUrl = str_replace('/'.$popup->id.'/events', '/'.($popup->id + 1).'/events', $eventUrl);
        $this->postJson($tamperedPathUrl, ['event' => 'impression'])->assertForbidden();
        $this->assertSame(0, PopupAdEvent::query()->count());

        // Without marketing_consent cookie, a valid event URL still declines recording.
        $this->postJson($eventUrl, ['event' => 'impression'])
            ->assertStatus(202)
            ->assertJson(['recorded' => false, 'reason' => 'consent_required']);
        $this->assertSame(0, PopupAdEvent::query()->count());

        // With marketing_consent cookie, impression and click are recorded cleanly without PII columns.
        $this->call('POST', $eventUrl, ['event' => 'impression'], ['marketing_consent' => 'accepted'])
            ->assertOk()
            ->assertJson(['recorded' => true]);

        $this->call('POST', $eventUrl, ['event' => 'click'], ['marketing_consent' => 'accepted'])
            ->assertOk()
            ->assertJson(['recorded' => true]);

        // The daily unique aggregate row is reused for every event type, and a
        // second impression is declined after the campaign reaches its cap.
        $this->call('POST', $eventUrl, ['event' => 'impression'], ['marketing_consent' => 'accepted'])
            ->assertStatus(202)
            ->assertJson(['recorded' => false, 'reason' => 'impression_cap_reached']);

        $this->assertDatabaseCount('popup_ad_events', 1);
        $this->assertFalse(Schema::hasColumn('popup_ad_events', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('popup_ad_events', 'user_id'));
        $this->assertDatabaseHas('popup_ad_events', [
            'popup_id' => $popup->id,
            'impressions' => 1,
            'clicks' => 1,
        ]);

        // Max impressions (1) reached -> popup no longer eligible on public feed.
        $this->getJson('/popups/active')->assertOk()->assertJson(['popup' => null]);

        // Non-admin access control checks.
        $this->actingAs($student)->get(route('admin.visitor-popups.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.visitor-popups.export'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.visitor-popups.purge'), [
            'confirm_purge' => '1',
            'confirmation_phrase' => 'ERASE POPUP DATA',
        ])->assertForbidden();

        // Admin export & confirmed purge.
        $exportResponse = $this->actingAs($admin)->get(route('admin.visitor-popups.export'))->assertOk();
        $exported = json_decode((string) $exportResponse->getContent(), true);
        $this->assertSame('aec.popup-ads', $exported['plugin']);
        $this->assertSame(1, $exported['campaigns_count']);

        $this->actingAs($admin)->post(route('admin.visitor-popups.purge'), [
            'confirm_purge' => '1',
            'confirmation_phrase' => 'WRONG PHRASE',
        ])->assertSessionHasErrors('confirmation_phrase');
        $this->assertDatabaseCount('visitor_popups', 1);

        $this->actingAs($admin)->post(route('admin.visitor-popups.purge'), [
            'confirm_purge' => '1',
            'confirmation_phrase' => 'ERASE POPUP DATA',
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $this->assertDatabaseCount('visitor_popups', 0);
        $this->assertDatabaseCount('popup_ad_events', 0);
    }

    public function test_every_visit_frequency_is_supported_and_daily_frequency_uses_campaign_timezone(): void
    {
        $this->activatePopupAdsPlugin();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.visitor-popups.store'), [
            'title' => 'Every visit notice',
            'popup_type' => 'announcement',
            'audience' => 'all',
            'frequency' => 'every_visit',
            'delay_seconds' => 0,
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $campaign = PopupCampaign::query()->firstOrFail();
        $this->assertSame('every_visit', $campaign->frequency);
        $this->assertSame('Asia/Dhaka', $campaign->toPublicPayload(null, false)['timezone']);
        $this->assertContains('every_visit', PopupCampaign::FREQUENCIES);
    }

}
