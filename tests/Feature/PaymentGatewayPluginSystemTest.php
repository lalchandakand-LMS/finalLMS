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

    public function test_admin_can_view_gateway_catalog_without_exposing_bkash_secrets(): void
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
            ->assertSee('bKash Tokenized Checkout')
            ->assertSee('BDT')
            ->assertDontSee('private-merchant-user')
            ->assertDontSee('private-merchant-password')
            ->assertDontSee('private-app-key')
            ->assertDontSee('private-app-secret');
    }

    public function test_admin_can_enable_a_configured_gateway_and_change_is_audited(): void
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
            ->assertRedirect(route('admin.payment-gateways.index'));

        $registry = json_decode((string) Setting::value('payment_gateway_registry'), true);
        $this->assertTrue($registry['bkash']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.payment_gateways.updated',
        ]);
    }

    public function test_unconfigured_gateway_cannot_be_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.payment-gateways.update', 'bkash'), ['enabled' => true])
            ->assertStatus(422);
        $this->assertSame([], json_decode((string) Setting::value('payment_gateway_registry', '[]'), true));
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
