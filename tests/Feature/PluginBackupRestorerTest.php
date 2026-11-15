<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginBackupRestorer;
use App\Services\Plugins\PluginUninstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PluginBackupRestorerTest extends TestCase
{
    use RefreshDatabase;

    private string $relative = 'plugins/restorevendor/sample-plugin/1.0.0';
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
        foreach ([
            base_path('plugins/restorevendor'),
            base_path('plugins/.backups/restorevendor'),
        ] as $directory) {
            if (is_dir($directory) && ! is_link($directory)) {
                File::deleteDirectory($directory);
            }
        }

        parent::tearDown();
    }

    private function installedPlugin(): PluginInstallation
    {
        $file = $this->absolute.'/src/Plugin.php';
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0750, true);
        }
        file_put_contents($file, '<?php return new class {};');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'restorevendor.sample-plugin',
            'name' => 'Sample Plugin',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => ['key' => 'restorevendor.sample-plugin', 'dependencies' => []],
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

    public function test_verified_private_backup_can_be_restored_and_remains_inactive(): void
    {
        $plugin = $this->installedPlugin();
        $uninstalled = app(PluginUninstaller::class)->uninstall($plugin);

        $restored = app(PluginBackupRestorer::class)->restore($uninstalled->fresh());

        $this->assertSame('inactive', $restored->status);
        $this->assertDirectoryExists($this->absolute);
        $this->assertFileExists($this->absolute.'/src/Plugin.php');
        $this->assertArrayNotHasKey('uninstall_backup_path', $restored->metadata);
        $this->assertNotEmpty($restored->metadata['restored_at']);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'restorevendor.sample-plugin',
            'status' => 'inactive',
        ]);
    }

    public function test_tampered_backup_is_not_restored_and_remains_in_private_backup(): void
    {
        $plugin = $this->installedPlugin();
        $uninstalled = app(PluginUninstaller::class)->uninstall($plugin);
        $backup = base_path($uninstalled->metadata['uninstall_backup_path']);
        file_put_contents($backup.'/src/Plugin.php', '<?php echo "tampered";');

        try {
            app(PluginBackupRestorer::class)->restore($uninstalled->fresh());
            $this->fail('A tampered backup must not be restored.');
        } catch (RuntimeException) {
            $this->assertDirectoryDoesNotExist($this->absolute);
            $this->assertDirectoryExists($backup);
            $this->assertSame('uninstalled', $uninstalled->fresh()->status);
        }
    }

    public function test_restore_rejects_a_missing_backup(): void
    {
        $plugin = $this->installedPlugin();
        $plugin->status = 'uninstalled';
        $plugin->metadata = ['uninstall_backup_path' => 'plugins/.backups/restorevendor/missing'];
        $plugin->save();

        $this->expectException(RuntimeException::class);
        app(PluginBackupRestorer::class)->restore($plugin);
    }
}
