<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginContext;
use App\Services\Plugins\PluginHookRegistry;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class PluginHookRegistryTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath = 'plugins/aec/course-studio/1.0.0';

    private string $keyPair;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = base_path($this->packagePath.'/src');
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/Plugin.php', "<?php // signed test plugin entrypoint\n");

        $this->keyPair = sodium_crypto_sign_keypair();
        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
        ]]);
    }

    protected function tearDown(): void
    {
        $directory = base_path($this->packagePath);
        if (is_dir($directory) && ! is_link($directory)) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    private function activePlugin(): PluginInstallation
    {
        $manifest = [
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
        ];
        $checksum = hash('sha256', 'signed test package');
        $signature = base64_encode(sodium_crypto_sign_detached(
            PluginSignatureVerifier::payloadForSignature($manifest, $checksum),
            sodium_crypto_sign_secretkey($this->keyPair),
        ));

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.course-studio',
            'name' => 'Course Studio',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => $manifest,
            'package_sha256' => $checksum,
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => $this->packagePath,
            'metadata' => [
                'granted_permissions' => ['courses.view'],
                'integrity_version' => 1,
                'release_signature' => $signature,
                'signature_record_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', base_path($this->packagePath.'/src/Plugin.php')),
                ],
            ],
        ])->save();

        return $plugin;
    }

    public function test_hooks_and_portal_navigation_are_available_only_while_the_signed_plugin_is_active(): void
    {
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
    public function test_hook_registry_hides_plugins_when_the_trusted_public_key_is_empty(): void
    {
        config(['plugins.trusted_public_keys' => ['test-release-key' => '']]);
        Route::get('/plugin-hook-empty-key', fn () => 'ok')->name('plugin-hook.empty-key');
        Route::getRoutes()->refreshNameLookups();

        $plugin = $this->activePlugin();
        $registry = app(PluginHookRegistry::class);
        $context = new PluginContext(app(), app('router'), $plugin, $registry);
        $context->registerHook('lms.dashboard.widgets', fn () => 'must-not-run');
        $context->registerPortalNavigation('student', 'Course Studio', 'plugin-hook.empty-key', [
            'permission' => 'courses.view',
        ]);

        $this->assertSame([], $registry->dispatch('lms.dashboard.widgets'));
        $this->assertSame([], $registry->portalNavigation('student'));
    }



    public function test_active_plugin_middleware_rejects_an_empty_trusted_public_key(): void
    {
        $this->activePlugin();
        config(['plugins.trusted_public_keys' => ['test-release-key' => '']]);
        Route::middleware('plugin.active:aec.course-studio')
            ->get('/plugins/course-studio/empty-key-guard', fn () => response('should not run'));

        $this->get('/plugins/course-studio/empty-key-guard')->assertNotFound();
    }


    public function test_hooks_and_navigation_reject_a_revoked_or_replaced_release_key(): void
    {
        Route::get('/plugin-hook-revoked-key', fn () => 'ok')->name('plugin-hook.revoked-key');
        Route::getRoutes()->refreshNameLookups();

        $plugin = $this->activePlugin();
        $registry = app(PluginHookRegistry::class);
        $context = new PluginContext(app(), app('router'), $plugin, $registry);
        $context->registerHook('lms.dashboard.widgets', fn () => 'must-not-run');
        $context->registerPortalNavigation('student', 'Course Studio', 'plugin-hook.revoked-key', [
            'permission' => 'courses.view',
        ]);

        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())),
        ]]);

        $this->assertSame([], $registry->dispatch('lms.dashboard.widgets'));
        $this->assertSame([], $registry->portalNavigation('student'));
    }

    public function test_hooks_and_navigation_reject_modified_installed_plugin_files(): void
    {
        Route::get('/plugin-hook-tampered', fn () => 'ok')->name('plugin-hook.tampered');
        Route::getRoutes()->refreshNameLookups();

        $plugin = $this->activePlugin();
        $registry = app(PluginHookRegistry::class);
        $context = new PluginContext(app(), app('router'), $plugin, $registry);
        $context->registerHook('lms.dashboard.widgets', fn () => 'must-not-run');
        $context->registerPortalNavigation('student', 'Course Studio', 'plugin-hook.tampered', [
            'permission' => 'courses.view',
        ]);

        file_put_contents(base_path($this->packagePath.'/src/Plugin.php'), '<?php // tampered after plugin boot');
        $this->assertSame([], $registry->dispatch('lms.dashboard.widgets'));
        $this->assertSame([], $registry->portalNavigation('student'));
    }


    public function test_plugin_route_middleware_rejects_a_replaced_signing_key(): void
    {
        $this->activePlugin();
        Route::middleware('plugin.active:aec.course-studio')
            ->get('/plugins/course-studio/revoked-key', fn () => response('must not run'));

        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())),
        ]]);

        $this->get('/plugins/course-studio/revoked-key')->assertNotFound();
    }

    public function test_plugin_route_middleware_rejects_tampered_installed_files_before_controller_execution(): void
    {
        $this->activePlugin();
        Route::middleware('plugin.active:aec.course-studio')
            ->get('/plugins/course-studio/tampered-file', fn () => response('must not run'));

        file_put_contents(base_path($this->packagePath.'/src/Plugin.php'), '<?php // tampered before route request');

        $this->get('/plugins/course-studio/tampered-file')->assertNotFound();
    }

}
