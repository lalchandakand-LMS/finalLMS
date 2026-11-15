<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PluginActivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath = 'plugins/test/activation-check/1.0.0';
    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->absolutePath = base_path($this->packagePath);
        if (! is_dir($this->absolutePath.'/src')) {
            mkdir($this->absolutePath.'/src', 0750, true);
        }

        config([
            'plugins.trusted_public_keys' => ['test-release-key' => 'dGVzdC1rZXk='],
            'plugins.lms_version' => '1.0.0',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->absolutePath) && is_dir($this->absolutePath) && ! is_link($this->absolutePath)) {
            File::deleteDirectory($this->absolutePath);
        }
        parent::tearDown();
    }

    private function createPlugin(
        string $key = 'test.activation-check',
        string $status = 'installed',
        array $dependencies = [],
        array $conflicts = [],
        string $phpConstraint = '^8.3',
    ): PluginInstallation {
        [$vendor, $slug] = explode('.', $key, 2);
        $relativePath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/1.0.0';
        $absolutePath = base_path($relativePath);
        if (! is_dir($absolutePath.'/src')) {
            mkdir($absolutePath.'/src', 0750, true);
        }

        $entrypoint = $absolutePath.'/src/Plugin.php';
        file_put_contents($entrypoint, '<?php return true;');

        $manifest = [
            'schema_version' => 1,
            'key' => $key,
            'name' => 'Activation Test',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => [
                'php' => $phpConstraint,
                'laravel' => '^12.0',
                'lms' => '^1.0',
            ],
            'dependencies' => $dependencies,
            'conflicts' => $conflicts,
            'portals' => ['admin'],
            'permissions' => ['activation.manage'],
            'capabilities' => ['activation'],
        ];

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $key,
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'type' => $manifest['type'],
            'status' => $status,
            'manifest' => $manifest,
            'package_sha256' => hash('sha256', 'signed archive '.$key),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => $relativePath,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $entrypoint),
                ],
            ],
        ])->save();

        return $plugin;
    }

    public function test_activates_installed_plugin_after_integrity_and_compatibility_checks(): void
    {
        $plugin = $this->createPlugin();

        $activated = app(PluginActivationService::class)->activate($plugin->plugin_key);

        $this->assertSame('active', $activated->status);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_activation_requires_all_declared_dependencies_to_be_active(): void
    {
        $plugin = $this->createPlugin(dependencies: ['test.required']);

        $this->expectException(InvalidArgumentException::class);
        app(PluginActivationService::class)->activate($plugin->plugin_key);
    }

    public function test_activation_rejects_an_active_conflicting_plugin(): void
    {
        $plugin = $this->createPlugin(conflicts: ['test.conflict']);
        $conflict = new PluginInstallation();
        $conflict->forceFill([
            'plugin_key' => 'test.conflict',
            'name' => 'Conflicting plugin',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => [],
        ])->save();

        $this->expectException(InvalidArgumentException::class);
        app(PluginActivationService::class)->activate($plugin->plugin_key);
    }

    public function test_activation_rejects_modified_package_files(): void
    {
        $plugin = $this->createPlugin();
        file_put_contents($this->absolutePath.'/src/Plugin.php', '<?php echo "tampered";');

        $this->expectException(RuntimeException::class);
        app(PluginActivationService::class)->activate($plugin->plugin_key);
    }

    public function test_activation_rejects_incompatible_php_version_constraint(): void
    {
        $plugin = $this->createPlugin(phpConstraint: '^99.0');

        $this->expectException(InvalidArgumentException::class);
        app(PluginActivationService::class)->activate($plugin->plugin_key);
    }

    public function test_activation_rejects_revoked_signing_key(): void
    {
        $plugin = $this->createPlugin();
        config(['plugins.trusted_public_keys' => []]);

        $this->expectException(RuntimeException::class);
        app(PluginActivationService::class)->activate($plugin->plugin_key);
    }

    public function test_deactivates_plugin_when_no_active_plugin_depends_on_it(): void
    {
        $plugin = $this->createPlugin(status: 'active');

        $deactivated = app(PluginActivationService::class)->deactivate($plugin->plugin_key);

        $this->assertSame('inactive', $deactivated->status);
    }

    public function test_deactivation_is_blocked_while_an_active_dependent_exists(): void
    {
        $plugin = $this->createPlugin(status: 'active');
        $dependent = new PluginInstallation();
        $dependent->forceFill([
            'plugin_key' => 'test.dependent',
            'name' => 'Dependent plugin',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => ['dependencies' => ['test.activation-check']],
        ])->save();

        $this->expectException(InvalidArgumentException::class);
        app(PluginActivationService::class)->deactivate($plugin->plugin_key);
    }
}
