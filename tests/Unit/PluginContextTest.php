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
        $context->apiRoutesForPortal('student', 'api/v1/plugins/mobile-test', 'api.plugins.mobile_test.', function (): void {
            Route::get('/health', fn () => response()->json(['ok' => true]))->name('health');
        }, 'mobile.read');

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === 'api/v1/plugins/mobile-test/health');

        $this->assertNotNull($route);
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
        $context->apiRoutesForPortal('guardian', 'api/v1/plugins/guardian-test', 'api.plugins.guardian_test.', function (): void {
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
        $this->assertContains('plugin.active:aec.support-staff-test', $route->middleware());
        $this->assertContains('role:accountant', $route->middleware());
        $this->assertContains('plugin.permission:aec.support-staff-test,support.test', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('verified', $route->middleware());
    }
}
