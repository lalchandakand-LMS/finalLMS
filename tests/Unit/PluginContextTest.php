<?php

namespace Tests\Unit;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PluginContextTest extends TestCase
{
    public function test_mobile_api_routes_require_device_token_role_and_declared_permission(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.mobile-test',
            'manifest' => ['portals' => ['student'], 'permissions' => ['mobile.read']],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);
        $context->apiRoutesForPortal('student', 'api/v1/plugins/aec/mobile-test', 'api.plugins.mobile_test.', function (): void {
            Route::get('/health', fn () => response()->json(['ok' => true]))->name('health');
        }, 'mobile.read');

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === 'api/v1/plugins/aec/mobile-test/health');

        $this->assertNotNull($route);
        $this->assertSame('api.plugins.mobile_test.health', $route->getName());
        $this->assertTrue(Route::has('api.plugins.mobile_test.health'));
        $this->assertContains('api', $route->middleware());
        $this->assertContains('plugin.active:aec.mobile-test', $route->middleware());
        $this->assertContains('api.token', $route->middleware());
        $this->assertContains('role:student', $route->middleware());
        $this->assertContains('plugin.permission:aec.mobile-test,mobile.read', $route->middleware());
        $this->assertContains('throttle:60,1', $route->middleware());
    }

    public function test_mobile_api_route_helper_rejects_guardian_portal_until_guardian_api_auth_exists(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.guardian-api-test',
            'manifest' => ['portals' => ['guardian'], 'permissions' => ['guardian.read']],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('student and teacher mobile portals');
        $context->apiRoutesForPortal('guardian', 'api/v1/plugins/aec/guardian-api-test', 'api.plugins.guardian_test.', function (): void {
            Route::get('/health', fn () => response()->json(['ok' => true]))->name('health');
        }, 'guardian.read');
    }

    public function test_support_staff_portal_is_explicitly_bound_to_the_accountant_role(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.support-staff-test',
            'manifest' => ['portals' => ['support_staff'], 'permissions' => ['support.test']],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);
        $context->routesForPortal('support_staff', 'test/support-ai', 'test.support_ai.', function (): void {
            Route::get('/health', fn () => response('ok'))->name('health');
        }, 'support.test');

        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($candidate) => $candidate->uri() === 'test/support-ai/health');
        $this->assertNotNull($route);
        $this->assertSame('test.support_ai.health', $route->getName());
        $this->assertTrue(Route::has('test.support_ai.health'));
        $this->assertContains('plugin.active:aec.support-staff-test', $route->middleware());
        $this->assertContains('role:accountant', $route->middleware());
        $this->assertContains('plugin.permission:aec.support-staff-test,support.test', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('verified', $route->middleware());
    }
    public function test_only_plugins_declaring_payment_gateway_capability_can_register_adapters(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.test-gateway',
            'manifest' => ['capabilities' => ['payments.gateway']],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);
        $context->registerPaymentGateway('test_gateway', TestPluginPaymentGateway::class);

        $this->assertContains('test_gateway', app(\App\Services\Payments\PaymentGatewayManager::class)->installedKeys());
    }

    public function test_plugin_without_payment_gateway_capability_cannot_register_adapter(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.untrusted-gateway',
            'manifest' => ['capabilities' => []],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payments.gateway capability');
        $context->registerPaymentGateway('untrusted_gateway', TestPluginPaymentGateway::class);
    }

    public function test_duplicate_gateway_keys_are_rejected(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.test-gateway',
            'manifest' => ['capabilities' => ['payments.gateway']],
        ]);
        $context = new PluginContext(app(), app('router'), $plugin);
        $context->registerPaymentGateway('test_gateway', TestPluginPaymentGateway::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');
        $context->registerPaymentGateway('test_gateway', TestPluginPaymentGateway::class);
    }
}

class TestPluginPaymentGateway implements \App\Contracts\Payments\PaymentGateway
{
    public function key(): string { return 'test_gateway'; }
    public function label(): string { return 'Test Plugin Gateway'; }
    public function supportedCurrencies(): array { return ['BDT']; }
    public function isConfigured(): bool { return false; }
    public function createCheckout(\App\Models\Payment $payment, string $callbackUrl, string $payerReference): array
    {
        return ['payment_id' => 'test-payment', 'checkout_url' => 'https://payments.example.test/checkout'];
    }
    public function callbackPaymentId(array $callback): ?string { return $callback['paymentID'] ?? null; }
    public function verifyCallback(\App\Models\Payment $payment, array $callback): array
    {
        return ['verified' => [], 'executed' => []];
    }
}
