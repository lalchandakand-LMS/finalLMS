<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginUninstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PluginUninstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $relative = 'plugins/uninstallvendor/sample-plugin/1.0.0';
    private string $absolute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->absolute = base_path($this->relative);
        if (! is_dir($this->absolute)) {
            mkdir($this->absolute, 0750, true);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->absolute)) {
            $root = base_path('plugins/uninstallvendor');
            if (is_dir($root) && ! is_link($root)) {
                File::deleteDirectory($root);
            }
        }

        $backupRoot = base_path('plugins/.backups/uninstallvendor');
        if (is_dir($backupRoot) && ! is_link($backupRoot)) {
            File::deleteDirectory($backupRoot);
        }

        parent::tearDown();
    }

    private function plugin(string $status = 'inactive', array $dependencies = []): PluginInstallation
    {
        $file = $this->absolute.'/src/Plugin.php';
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0750, true);
        }
        file_put_contents($file, '<?php return new class {};');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'uninstallvendor.sample-plugin',
            'name' => 'Sample Plugin',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => ['key' => 'uninstallvendor.sample-plugin', 'dependencies' => $dependencies],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $file),
                ],
            ],
        ])->save();

        return $plugin;
    }

    public function test_uninstall_moves_verified_files_to_private_backup_and_keeps_registry(): void
    {
        $plugin = $this->plugin();

        $uninstalled = app(PluginUninstaller::class)->uninstall($plugin);

        $backup = base_path($uninstalled->metadata['uninstall_backup_path']);
        $this->assertSame('uninstalled', $uninstalled->status);
        $this->assertDirectoryExists($backup);
        $this->assertFileExists($backup.'/src/Plugin.php');
        $this->assertDirectoryDoesNotExist($this->absolute);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'uninstallvendor.sample-plugin',
            'status' => 'uninstalled',
        ]);
        $this->assertNotEmpty($uninstalled->metadata['uninstalled_at']);
    }

    public function test_uninstall_is_blocked_while_another_plugin_depends_on_it(): void
    {
        $plugin = $this->plugin();
        $dependent = new PluginInstallation();
        $dependent->forceFill([
            'plugin_key' => 'uninstallvendor.dependent-plugin',
            'name' => 'Dependent Plugin',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['key' => 'uninstallvendor.dependent-plugin', 'dependencies' => [$plugin->plugin_key]],
        ])->save();

        $this->expectException(RuntimeException::class);
        app(PluginUninstaller::class)->uninstall($plugin);
    }

    public function test_active_plugin_must_be_deactivated_before_uninstall(): void
    {
        $plugin = $this->plugin('active');

        $this->expectException(RuntimeException::class);
        app(PluginUninstaller::class)->uninstall($plugin);
    }

    public function test_uninstall_rejects_tampered_package_files(): void
    {
        $plugin = $this->plugin();
        file_put_contents($this->absolute.'/src/Plugin.php', '<?php echo "tampered";');

        $this->expectException(RuntimeException::class);
        app(PluginUninstaller::class)->uninstall($plugin);
    }
}
