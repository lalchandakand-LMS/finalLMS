<?php

namespace Tests\Feature;

use App\Contracts\Plugins\PluginInterface;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginRuntime;
use App\Services\Plugins\PluginTreeHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PluginRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $pluginDirectory;
    private string $markerPath;
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginDirectory = base_path('plugins/aec/runtime-demo/1.0.0');
        $this->markerPath = storage_path('framework/testing/plugin-runtime-executed.marker');

        if (is_dir($this->pluginDirectory)) {
            File::deleteDirectory($this->pluginDirectory);
        }

        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        if (! is_dir(dirname($this->markerPath))) {
            mkdir(dirname($this->markerPath), 0700, true);
        }

        $this->manifest = [
            'schema_version' => 1,
            'key' => 'aec.runtime-demo',
            'name' => 'Runtime Demo',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => ['admin'],
            'permissions' => ['runtime-demo.view'],
            'capabilities' => ['runtime-demo'],
        ];

        config(['plugins.trusted_public_keys' => [
            'test-release-key' => 'test-public-key-placeholder',
        ]]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->pluginDirectory) && ! is_link($this->pluginDirectory)) {
            File::deleteDirectory($this->pluginDirectory);
        }

        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        parent::tearDown();
    }

    private function createPackage(string $entrypointContents, string $routeContents = ''): PluginInstallation
    {
        $routesDirectory = $this->pluginDirectory.'/routes';
        if (! is_dir($routesDirectory)) {
            mkdir($routesDirectory, 0750, true);
        }

        file_put_contents($this->pluginDirectory.'/Plugin.php', $entrypointContents);
        if ($routeContents !== '') {
            file_put_contents($routesDirectory.'/web.php', $routeContents);
        }

        $treeHash = app(PluginTreeHasher::class)->hash($this->pluginDirectory);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $this->manifest['key'],
            'name' => $this->manifest['name'],
            'version' => $this->manifest['version'],
            'type' => $this->manifest['type'],
            'status' => 'active',
            'manifest' => $this->manifest,
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/runtime-demo/1.0.0',
            'metadata' => ['installed_tree_sha256' => $treeHash],
        ])->save();

        return $plugin;
    }

    private function markerEntrypoint(): string
    {
        return "<?php\nfile_put_contents(".var_export($this->markerPath, true).", 'executed');\n\n"
            ."return new class implements \\App\\Contracts\\Plugins\\PluginInterface {\n"
            ."    public function register(\\App\\Services\\Plugins\\PluginContext $context): void {}\n"
            ."};\n";
    }

    private function validEntrypoint(): string
    {
        return <<<'PHP'
<?php

return new class implements \App\Contracts\Plugins\PluginInterface {
    public function register(\App\Services\Plugins\PluginContext $context): void
    {
        $context->webRoutes('routes/web.php');
    }
};
PHP;
    }

    private function validRoutes(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', static fn () => response()->json(['plugin' => 'runtime-demo']));
PHP;
    }

    public function test_active_integrity_verified_plugin_registers_guarded_web_routes(): void
    {
        $plugin = $this->createPackage($this->validEntrypoint(), $this->validRoutes());

        app(PluginRuntime::class)->register($plugin);

        $user = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($user)
            ->get('/plugins/aec/runtime-demo/ping')
            ->assertOk()
            ->assertJson(['plugin' => 'runtime-demo']);

        $this->assertSame('healthy', $plugin->fresh()->health_status);
    }

    public function test_runtime_rejects_tampered_plugin_before_loading_entrypoint(): void
    {
        $entrypoint = <<<'PHP'
<?php

file_put_contents(__DIR__.'/../../../../../../storage/framework/testing/plugin-runtime-executed.marker', 'executed');

return new class implements \App\Contracts\Plugins\PluginInterface {
    public function register(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;

        $plugin = $this->createPackage($entrypoint, $this->validRoutes());
        file_put_contents($this->pluginDirectory.'/routes/web.php', '<?php // tampered after installation');

        $this->expectException(RuntimeException::class);
        try {
            app(PluginRuntime::class)->register($plugin);
        } finally {
            $this->assertFileDoesNotExist($this->markerPath);
        }
    }

    public function test_inactive_plugin_entrypoint_is_never_loaded(): void
    {
        $entrypoint = <<<'PHP'
<?php

file_put_contents(__DIR__.'/../../../../../../storage/framework/testing/plugin-runtime-executed.marker', 'executed');

return new class implements \App\Contracts\Plugins\PluginInterface {
    public function register(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;

        $plugin = $this->createPackage($entrypoint);
        $plugin->status = 'inactive';
        $plugin->save();

        $this->expectException(RuntimeException::class);
        try {
            app(PluginRuntime::class)->register($plugin);
        } finally {
            $this->assertFileDoesNotExist($this->markerPath);
        }
    }

    public function test_runtime_auto_disables_plugin_when_entrypoint_contract_fails(): void
    {
        $plugin = $this->createPackage('<?php return new stdClass();');

        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('inactive', $plugin->fresh()->status);
        $this->assertSame('failed', $plugin->fresh()->health_status);
    }

    public function test_plugin_active_middleware_blocks_routes_after_deactivation(): void
    {
        $plugin = $this->createPackage($this->validEntrypoint(), $this->validRoutes());
        app(PluginRuntime::class)->register($plugin);

        $plugin->status = 'inactive';
        $plugin->save();

        $user = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($user)->get('/plugins/aec/runtime-demo/ping')->assertNotFound();
    }
}
