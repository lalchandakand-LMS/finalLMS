<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MfsMerchantConfiguration;
use App\Models\User;
use App\Services\Payments\BkashCheckoutService;
use App\Services\Payments\MfsMerchantConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MfsMerchantConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_sandbox_mfs_credentials_encrypted_without_rendering_them_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $secret = 'mfs-super-secret-value';
        $this->actingAs($admin)->put(route('admin.payment-gateways.mfs.update', 'bkash'), [
            'mode' => 'sandbox',
            'credentials_json' => json_encode([
                'username' => 'sandbox-merchant', 'password' => $secret,
                'app_key' => 'sandbox-app-key', 'app_secret' => 'sandbox-app-secret',
            ], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('admin.payment-gateways.index'));

        $record = MfsMerchantConfiguration::query()->where('provider', 'bkash')->firstOrFail();
        $this->assertSame('sandbox', $record->mode);
        $this->assertSame($secret, $record->credentials['password']);
        $this->assertStringNotContainsString($secret, $record->getRawOriginal('credentials'));
        $this->assertSame($admin->id, $record->updated_by);
        $this->actingAs($admin)->get(route('admin.payment-gateways.index'))
            ->assertOk()->assertSee('bKash')->assertSee('Credentials saved')
            ->assertDontSee($secret)->assertDontSee('sandbox-merchant');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'event' => 'admin.mfs_merchant_configuration.updated']);
        $this->assertStringNotContainsString($secret, ActivityLog::query()->latest('id')->value('description'));
    }

    public function test_admin_can_store_nagad_and_rocket_configuration_without_installing_adapters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        foreach (['nagad', 'rocket'] as $provider) {
            $this->actingAs($admin)->put(route('admin.payment-gateways.mfs.update', $provider), [
                'mode' => 'sandbox',
                'credentials_json' => '{"merchant_reference":"sandbox-reference","api_secret":"secret-value"}',
            ])->assertRedirect(route('admin.payment-gateways.index'));
        }
        $this->assertDatabaseCount('mfs_merchant_configurations', 2);
        $this->assertSame(['bkash'], app(\App\Services\Payments\PaymentGatewayManager::class)->installedKeys());
    }

    public function test_invalid_provider_mode_json_and_nested_values_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin);
        $this->put(route('admin.payment-gateways.mfs.update', 'unknown'), [
            'mode' => 'sandbox', 'credentials_json' => '{"key":"value"}',
        ])->assertNotFound();
        $this->put(route('admin.payment-gateways.mfs.update', 'bkash'), [
            'mode' => 'live', 'credentials_json' => '{"key":"value"}',
        ])->assertSessionHasErrors('mode');
        $this->put(route('admin.payment-gateways.mfs.update', 'bkash'), [
            'mode' => 'sandbox', 'credentials_json' => '[{"key":"value"}]',
        ])->assertSessionHasErrors('credentials_json');
        $this->put(route('admin.payment-gateways.mfs.update', 'bkash'), [
            'mode' => 'sandbox', 'credentials_json' => '{"nested":{"secret":"value"}}',
        ])->assertSessionHasErrors('credentials_json');
        $this->assertDatabaseCount('mfs_merchant_configurations', 0);
    }

    public function test_non_admin_cannot_store_mfs_merchant_credentials(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student)->put(route('admin.payment-gateways.mfs.update', 'bkash'), [
            'mode' => 'sandbox', 'credentials_json' => '{"password":"must-not-save"}',
        ])->assertForbidden();
        $this->assertDatabaseCount('mfs_merchant_configurations', 0);
    }

    public function test_database_bkash_credentials_override_environment_credentials(): void
    {
        config([
            'services.bkash.enabled' => false,
            'services.bkash.username' => 'legacy-user',
            'services.bkash.password' => 'legacy-password',
            'services.bkash.app_key' => 'legacy-app-key',
            'services.bkash.app_secret' => 'legacy-app-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.verify_ssl' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        app(MfsMerchantConfigurationService::class)->save('bkash', [
            'username' => 'database-user', 'password' => 'database-password',
            'app_key' => 'database-app-key', 'app_secret' => 'database-app-secret',
        ], $admin->id);
        $service = app(MfsMerchantConfigurationService::class);
        $this->assertSame('database-user', $service->credential('bkash', 'username'));
        $this->assertTrue($service->isBkashConfigured());
    }

    public function test_production_bkash_endpoint_is_never_accepted_by_sandbox_configuration(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'legacy-user',
            'services.bkash.password' => 'legacy-password',
            'services.bkash.app_key' => 'legacy-app-key',
            'services.bkash.app_secret' => 'legacy-app-secret',
            'services.bkash.base_url' => 'https://tokenized.pay.bka.sh/v1.2.0',
            'services.bkash.verify_ssl' => true,
        ]);

        $this->assertFalse(app(MfsMerchantConfigurationService::class)->isBkashConfigured());
        Http::fake();
        try {
            app(BkashCheckoutService::class)->grantToken();
            $this->fail('The checkout client must reject production endpoints before sending credentials.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sandbox endpoint', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
