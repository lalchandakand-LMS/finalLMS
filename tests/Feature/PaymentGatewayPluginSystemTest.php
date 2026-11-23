<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayPluginSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_catalog_does_not_advertise_provider_adapters_until_a_trusted_plugin_registers_them(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'private-merchant-user',
            'services.bkash.password' => 'private-merchant-password',
            'services.bkash.app_key' => 'private-app-key',
            'services.bkash.app_secret' => 'private-app-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.verify_ssl' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.payment-gateways.index'))
            ->assertOk()
            ->assertSee('Universal Payment Gateways')
            ->assertDontSee('bKash Tokenized Checkout')
            ->assertDontSee('BDT')
            ->assertDontSee('private-merchant-user')
            ->assertDontSee('private-merchant-password')
            ->assertDontSee('private-app-key')
            ->assertDontSee('private-app-secret');
    }

    public function test_admin_cannot_enable_a_gateway_without_an_installed_adapter(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'merchant-user',
            'services.bkash.password' => 'configured-password',
            'services.bkash.app_key' => 'configured-key',
            'services.bkash.app_secret' => 'configured-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.verify_ssl' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.payment-gateways.update', 'bkash'), ['enabled' => true])
            ->assertNotFound();

        $this->assertNull(Setting::value('payment_gateway_registry'));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_unconfigured_gateway_cannot_be_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.payment-gateways.update', 'bkash'), ['enabled' => true])
            ->assertNotFound();
        $this->assertNull(Setting::value('payment_gateway_registry'));
    }

    public function test_non_admin_cannot_view_or_change_payment_gateways(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.payment-gateways.index'))->assertForbidden();
        $this->patch(route('admin.payment-gateways.update', 'bkash'), ['enabled' => false])->assertForbidden();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_explicit_registry_disable_blocks_gateway_even_when_credentials_are_present(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.username' => 'merchant-user',
            'services.bkash.password' => 'configured-password',
            'services.bkash.app_key' => 'configured-key',
            'services.bkash.app_secret' => 'configured-secret',
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.verify_ssl' => true,
        ]);
        Setting::setValue('payment_gateway_registry', json_encode(['bkash' => false]));

        $this->assertFalse(app(\App\Services\Payments\PaymentGatewayManager::class)->isAvailable('bkash'));
    }

    public function test_only_registered_gateway_adapters_can_be_resolved(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\Payments\PaymentGatewayManager::class)->driver('untrusted_provider');
    }

}
