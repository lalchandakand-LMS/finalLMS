<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath = 'plugins/test/runtime-check/1.0.0';
    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->absolutePath = base_path($this->packagePath);
        if (! is_dir($this->absolutePath)) {
            mkdir($this->absolutePath, 0750, true);
        }

        config(['plugins.trusted_public_keys' => ['test-release-key' => 'dGVzdC1rZXk=']]);
    }

    protected function tearDown(): void
    {
        if (isset($this->absolutePath) && is_dir($this->absolutePath) && ! is_link($this->absolutePath)) {
            File::deleteDirectory($this->absolutePath);
        }
        parent::tearDown();
    }

    private function createPlugin(string $entrypoint, string $status = 'active', array $portals = ['admin']): PluginInstallation
    {
        $sourceDirectory = $this->absolutePath.'/src';
        if (! is_dir($sourceDirectory)) {
            mkdir($sourceDirectory, 0750, true);
        }

        $entrypointPath = $sourceDirectory.'/Plugin.php';
        file_put_contents($entrypointPath, $entrypoint);

        $manifest = [
            'schema_version' => 1,
            'key' => 'test.runtime-check',
            'name' => 'Runtime Check',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => $portals,
            'permissions' => ['runtime.check'],
            'capabilities' => ['runtime'],
        ];

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $manifest['key'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'type' => $manifest['type'],
            'status' => $status,
            'manifest' => $manifest,
            'package_sha256' => hash('sha256', 'signed test archive'),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => $this->packagePath,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $entrypointPath),
                ],
            ],
        ])->save();

        return $plugin;
    }

    private function validEntrypoint(string $portal = 'admin'): string
    {
        $source = <<<'PHP'
<?php

return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void
    {
        $context->routesForPortal('PORTAL', 'plugins/runtime-check', 'plugins.runtime_check.', function (): void {
            \Illuminate\Support\Facades\Route::get('/health', fn () => response()->json(['ok' => true]))->name('health');
        });
        $context->instance('plugin.runtime.registered', true);
    }

    public function boot(\App\Services\Plugins\PluginContext $context): void
    {
        $context->instance('plugin.runtime.booted', true);
    }
};
PHP;

        return str_replace("'PORTAL'", "'".$portal."'", $source);
    }

    public function test_loads_only_verified_active_plugin_and_registers_portal_scoped_routes(): void
    {
        $this->createPlugin($this->validEntrypoint());

        $runtime = app(PluginRuntime::class);
        $runtime->registerActivePlugins();
        $runtime->bootActivePlugins();

        $this->assertTrue(app('plugin.runtime.registered'));
        $this->assertTrue(app('plugin.runtime.booted'));

        $routes = app('router')->getRoutes()->getRoutes();
        $route = collect($routes)->first(fn ($candidate) => $candidate->uri() === 'plugins/runtime-check/health');
        $this->assertNotNull($route, 'Registered route URIs: '.json_encode(array_map(fn ($candidate) => $candidate->uri(), $routes)));
        $this->assertContains('plugin.active:test.runtime-check', $route->middleware());
        $this->assertContains('role:admin', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('verified', $route->middleware());

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/plugins/runtime-check/health')->assertOk()->assertJson(['ok' => true]);

        PluginInstallation::query()->where('plugin_key', 'test.runtime-check')->update(['status' => 'inactive']);
        $this->actingAs($admin)->get('/plugins/runtime-check/health')->assertNotFound();
    }


    public function test_inactive_plugin_is_never_loaded(): void
    {
        $this->createPlugin($this->validEntrypoint(), 'inactive');

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertFalse(app()->bound('plugin.runtime.registered'));
        $this->assertNull(app('router')->getRoutes()->getByName('plugins.runtime_check.health'));
    }

    public function test_tampered_plugin_is_failed_before_entrypoint_execution(): void
    {
        $plugin = $this->createPlugin($this->validEntrypoint());
        file_put_contents($this->absolutePath.'/src/Plugin.php', '<?php file_put_contents(storage_path("plugin-runtime-executed"), "yes");');

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('failed', $plugin->fresh()->status);
        $this->assertFalse(file_exists(storage_path('plugin-runtime-executed')));
        $this->assertFalse(app()->bound('plugin.runtime.registered'));
    }

    public function test_plugin_cannot_register_a_portal_not_declared_in_its_manifest(): void
    {
        $plugin = $this->createPlugin($this->validEntrypoint('student'), 'active', ['admin']);

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('failed', $plugin->fresh()->status);
        $this->assertFalse(app()->bound('plugin.runtime.registered'));
    }

    public function test_entrypoint_must_return_plugin_contract_instance(): void
    {
        $plugin = $this->createPlugin('<?php return "not-a-plugin";');

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('failed', $plugin->fresh()->status);
    }

    public function test_revoked_signing_key_prevents_plugin_loading(): void
    {
        $plugin = $this->createPlugin($this->validEntrypoint());
        config(['plugins.trusted_public_keys' => []]);

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('failed', $plugin->fresh()->status);
        $this->assertFalse(app()->bound('plugin.runtime.registered'));
    }
}
