<?php

namespace Tests\\Feature;

use App\\Models\\PluginInstallation;
use App\\Services\\Plugins\\PluginContext;
use App\\Services\\Plugins\\PluginHookRegistry;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Route;
use InvalidArgumentException;
use Tests\\TestCase;

class PluginHookRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function activePlugin(): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.course-studio',
            'name' => 'Course Studio',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => [
                'schema_version' => 1,
                'key' => 'aec.course-studio',
                'name' => 'Course Studio',
                'version' => '1.0.0',
                'type' => 'module',
                'entrypoint' => 'src/Plugin.php',
                'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
                'portals' => ['admin', 'branch', 'teacher', 'student', 'guardian'],
                'permissions' => ['courses.view'],
                'capabilities' => ['course-studio'],
            ],
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/course-studio/1.0.0',
            'metadata' => ['granted_permissions' => ['courses.view']],
        ])->save();

        return $plugin;
    }

    public function test_hooks_and_portal_navigation_are_available_only_while_the_signed_plugin_is_active(): void
    {
        config(['plugins.trusted_public_keys' => ['test-release-key' => base64_encode(random_bytes(32))]]);
        Route::get('/plugin-hook-test', fn () => 'ok')->name('plugin-hook.test');
        Route::getRoutes()->refreshNameLookups();
        $this->assertTrue(Route::has('plugin-hook.test'));

        $plugin = $this->activePlugin();
        $registry = app(PluginHookRegistry::class);
        $registry->registerCapabilities($plugin->plugin_key, ['course-studio', 'admin.superuser']);

        $context = new PluginContext(app(), app('router'), $plugin, $registry);
        $context->registerHook('lms.dashboard.widgets', fn (array $payload) => 'widget-'.$payload['role'], 5);
        $context->registerPortalNavigation('student', 'Course Studio', 'plugin-hook.test', [
            'icon' => 'book-open',
            'permission' => 'courses.view',
        ]);

        $this->assertTrue($registry->hasCapability('course-studio'));
        $this->assertFalse($registry->hasCapability('admin.superuser'));
        $this->assertTrue($context->hasPermission('courses.view'));
        $this->assertTrue($context->hasCapability('course-studio'));
        $this->assertSame(['widget-teacher'], $registry->dispatch('lms.dashboard.widgets', ['role' => 'teacher']));
        $this->assertCount(1, $registry->portalNavigation('student'));

        $plugin->forceFill(['metadata' => ['granted_permissions' => []]])->save();
        $this->assertSame([], $registry->portalNavigation('student'));
        $plugin->forceFill(['metadata' => ['granted_permissions' => ['courses.view']]])->save();

        $plugin->forceFill(['status' => 'inactive'])->save();

        $this->assertFalse($registry->hasCapability('course-studio'));
        $this->assertSame([], $registry->dispatch('lms.dashboard.widgets', ['role' => 'teacher']));
        $this->assertSame([], $registry->portalNavigation('student'));
    }

    public function test_plugin_navigation_cannot_claim_an_undeclared_permission(): void
    {
        $plugin = $this->activePlugin();
        $context = new PluginContext(app(), app('router'), $plugin, app(PluginHookRegistry::class));

        $this->expectException(InvalidArgumentException::class);
        $context->registerPortalNavigation('student', 'Course Studio', 'dashboard', [
            'permission' => 'admin.users.delete',
        ]);
    }

    public function test_authenticated_plugin_navigation_requires_a_declared_permission(): void
    {
        $plugin = $this->activePlugin();
        $context = new PluginContext(app(), app('router'), $plugin, app(PluginHookRegistry::class));

        $this->expectException(InvalidArgumentException::class);
        $context->registerPortalNavigation('student', 'Course Studio', 'dashboard');
    }

    public function test_authenticated_plugin_routes_still_require_declared_permissions(): void
    {
        $plugin = $this->activePlugin();
        $context = new PluginContext(app(), app('router'), $plugin, app(PluginHookRegistry::class));

        $this->expectException(InvalidArgumentException::class);
        $context->routesForPortal('student', 'plugin-course-studio', 'plugin.course_studio.', function (): void {
            Route::get('/', fn () => 'ok');
        });
    }
}
