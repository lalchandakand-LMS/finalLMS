<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_allow_list_without_exposing_provider_secrets(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'merchant-user',
            'services.bkash.password' => 'never-render-this-password',
            'services.bkash.app_key' => 'private-app-key',
            'services.bkash.app_secret' => 'private-app-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.addons.index'))
            ->assertOk()
            ->assertSee('Allow-listed Add-on Registry')
            ->assertSee('bKash Tokenized Checkout')
            ->assertSee('payments.verify')
            ->assertSee('Nagad Direct API')
            ->assertSee('Blocked pending official merchant API guide')
            ->assertDontSee('never-render-this-password')
            ->assertDontSee('private-app-key')
            ->assertDontSee('private-app-secret');
    }

    public function test_admin_can_view_configured_s3_compatible_storage_without_exposing_credentials(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'private-s3-key',
            'filesystems.disks.s3.secret' => 'private-s3-secret',
            'filesystems.disks.s3.region' => 'ap-southeast-1',
            'filesystems.disks.s3.bucket' => 'lms-private',
            'filesystems.disks.s3.endpoint' => 'https://objects.example.test',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.addons.index'))
            ->assertOk()
            ->assertSee('S3-compatible Object Storage')
            ->assertSee('storage.objects.write')
            ->assertSee('S3 configuration ready')
            ->assertDontSee('private-s3-key')
            ->assertDontSee('private-s3-secret');
    }

    public function test_s3_addon_cannot_be_enabled_with_an_insecure_custom_endpoint(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'configured-key',
            'filesystems.disks.s3.secret' => 'configured-secret',
            'filesystems.disks.s3.region' => 'ap-southeast-1',
            'filesystems.disks.s3.bucket' => 'lms-private',
            'filesystems.disks.s3.endpoint' => 'http://objects.example.test',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.addons.update', 's3_compatible_storage'), ['enabled' => true])
            ->assertStatus(422);
    }

    public function test_s3_addon_rejects_malformed_https_endpoint(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'configured-key',
            'filesystems.disks.s3.secret' => 'configured-secret',
            'filesystems.disks.s3.region' => 'ap-southeast-1',
            'filesystems.disks.s3.bucket' => 'lms-private',
            'filesystems.disks.s3.endpoint' => 'https:objects.example.test',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.addons.update', 's3_compatible_storage'), ['enabled' => true])
            ->assertStatus(422);
    }

    public function test_s3_addon_rejects_endpoint_with_embedded_credentials(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'configured-key',
            'filesystems.disks.s3.secret' => 'configured-secret',
            'filesystems.disks.s3.region' => 'ap-southeast-1',
            'filesystems.disks.s3.bucket' => 'lms-private',
            'filesystems.disks.s3.endpoint' => 'https://user:password@objects.example.test',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.addons.update', 's3_compatible_storage'), ['enabled' => true])
            ->assertStatus(422);
    }

    public function test_admin_can_enable_configured_addon_and_change_is_audited(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'merchant-user',
            'services.bkash.password' => 'configured-password',
            'services.bkash.app_key' => 'configured-key',
            'services.bkash.app_secret' => 'configured-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.addons.update', 'bkash'), ['enabled' => true])
            ->assertRedirect(route('admin.addons.index'));

        $registry = json_decode((string) Setting::value('addon_registry'), true);
        $this->assertTrue($registry['bkash']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.addons.updated',
        ]);
    }

    public function test_unconfigured_or_blocked_addons_cannot_be_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.addons.update', 'bkash'), ['enabled' => true])->assertStatus(422);
        $this->patch(route('admin.addons.update', 'nagad_api'), ['enabled' => true])->assertStatus(422);
        $this->assertSame([], json_decode((string) Setting::value('addon_registry', '[]'), true));
    }

    public function test_addon_registry_is_available_in_bangla_locale(): void
    {
        app()->setLocale('bn');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.addons.index'))
            ->assertOk()
            ->assertSee('অনুমোদিত অ্যাড-অন রেজিস্ট্রি')
            ->assertSee('বিকাশ টোকেনাইজড চেকআউট')
            ->assertSee('নগদ ডাইরেক্ট API')
            ->assertSee('নিরাপত্তা ও অবস্থা');
    }

    public function test_non_admin_cannot_view_or_change_addon_registry(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.addons.index'))->assertForbidden();
        $this->patch(route('admin.addons.update', 'bkash'), ['enabled' => false])->assertForbidden();
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
