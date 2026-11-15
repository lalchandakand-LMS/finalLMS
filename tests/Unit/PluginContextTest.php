<?php

namespace Tests\Unit;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PluginContextTest extends TestCase
{
    public function test_support_staff_portal_is_explicitly_bound_to_the_accountant_role(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.support-staff-test',
            'manifest' => ['portals' => ['support_staff']],
        ]);

        $context = new PluginContext(app(), app('router'), $plugin);
        $context->routesForPortal('support_staff', 'test/support-ai', 'test.support_ai.', function (): void {
            Route::get('/health', fn () => response('ok'))->name('health');
        });

        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($candidate) => $candidate->uri() === 'test/support-ai/health');
        $this->assertNotNull($route);
        $this->assertContains('plugin.active:aec.support-staff-test', $route->middleware());
        $this->assertContains('role:accountant', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('verified', $route->middleware());
    }
}
