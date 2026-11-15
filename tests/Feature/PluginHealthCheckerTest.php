<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginHealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = base_path('plugins/healthvendor/health-check/1.0.0');
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0750, true);
        }
        config(['plugins.trusted_public_keys' => ['release-key' => 'trusted-public-key-placeholder']]);
    }

    protected function tearDown(): void
    {
        $root = base_path('plugins/healthvendor');
        if (is_dir($root) && ! is_link($root)) {
            File::deleteDirectory($root);
        }
        parent::tearDown();
    }

    private function plugin(): PluginInstallation
    {
        $path = $this->directory.'/src/Plugin.php';
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        file_put_contents($path, '<?php return new class {};');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'healthvendor.health-check',
            'name' => 'Health Check',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => ['key' => 'healthvendor.health-check'],
            'signature_verified_at' => now(),
            'signature_key_id' => 'release-key',
            'package_sha256' => str_repeat('a', 64),
            'package_path' => 'plugins/healthvendor/health-check/1.0.0',
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $path),
                ],
            ],
        ])->save();

        return $plugin;
    }

    public function test_healthy_signed_package_records_integrity_status(): void
    {
        $checked = app(PluginHealthChecker::class)->check($this->plugin());

        $this->assertSame('healthy', $checked->health_status);
        $this->assertNotNull($checked->last_health_check_at);
        $this->assertStringContainsString('entrypoints remain unloaded', $checked->health_message);
    }

    public function test_tampered_package_is_marked_failed_without_executing_it(): void
    {
        $plugin = $this->plugin();
        file_put_contents($this->directory.'/src/Plugin.php', '<?php echo "tampered";');

        $checked = app(PluginHealthChecker::class)->check($plugin);

        $this->assertSame('failed', $checked->health_status);
        $this->assertStringContainsString('integrity or trust verification failed', $checked->health_message);
    }

    public function test_package_with_revoked_or_unknown_key_is_marked_failed(): void
    {
        $plugin = $this->plugin();
        config(['plugins.trusted_public_keys' => []]);

        $checked = app(PluginHealthChecker::class)->check($plugin);

        $this->assertSame('failed', $checked->health_status);
    }
}
