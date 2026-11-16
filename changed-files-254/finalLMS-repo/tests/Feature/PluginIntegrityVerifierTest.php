<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PluginIntegrityVerifierTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath;
    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packagePath = 'plugins/integrityvendor/integrity-check/1.0.0';
        $this->absolutePath = base_path($this->packagePath);
        if (! is_dir($this->absolutePath)) {
            mkdir($this->absolutePath, 0750, true);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->absolutePath) && is_dir($this->absolutePath) && ! is_link($this->absolutePath)) {
            File::deleteDirectory($this->absolutePath);
        }

        parent::tearDown();
    }

    private function registeredPlugin(array $files = ['src/Plugin.php' => '<?php return new class {};']): PluginInstallation
    {
        $hashes = [];
        foreach ($files as $relative => $contents) {
            $path = $this->absolutePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $directory = dirname($path);
            if (! is_dir($directory)) {
                mkdir($directory, 0750, true);
            }
            file_put_contents($path, $contents);
            $hashes[$relative] = hash_file('sha256', $path);
        }

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'integrityvendor.integrity-check',
            'name' => 'Integrity Check',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'package_path' => $this->packagePath,
            'manifest' => [],
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => $hashes,
            ],
        ])->save();

        return $plugin;
    }

    public function test_accepts_exact_installed_file_set_and_checksums(): void
    {
        $plugin = $this->registeredPlugin([
            'src/Plugin.php' => '<?php return new class {};',
            'resources/course.json' => '{"name":"Spoken English"}',
        ]);

        app(PluginIntegrityVerifier::class)->verify($plugin);

        $this->assertTrue(true);
    }

    public function test_rejects_modified_installed_file(): void
    {
        $plugin = $this->registeredPlugin();
        file_put_contents($this->absolutePath.'/src/Plugin.php', '<?php echo "tampered";');

        $this->expectException(RuntimeException::class);
        app(PluginIntegrityVerifier::class)->verify($plugin);
    }

    public function test_rejects_unexpected_file_added_after_installation(): void
    {
        $plugin = $this->registeredPlugin();
        file_put_contents($this->absolutePath.'/extra.php', '<?php echo "untrusted";');

        $this->expectException(RuntimeException::class);
        app(PluginIntegrityVerifier::class)->verify($plugin);
    }

    public function test_rejects_missing_installed_file(): void
    {
        $plugin = $this->registeredPlugin();
        unlink($this->absolutePath.'/src/Plugin.php');

        $this->expectException(RuntimeException::class);
        app(PluginIntegrityVerifier::class)->verify($plugin);
    }

    public function test_rejects_traversal_in_registry_integrity_manifest(): void
    {
        $plugin = $this->registeredPlugin();
        $metadata = $plugin->metadata;
        $metadata['installed_file_hashes'] = ['../outside.php' => str_repeat('a', 64)];
        $plugin->metadata = $metadata;
        $plugin->save();

        $this->expectException(RuntimeException::class);
        app(PluginIntegrityVerifier::class)->verify($plugin);
    }

    public function test_rejects_symlinked_parent_directory_even_when_target_is_inside_plugins_root(): void
    {
        $plugin = $this->registeredPlugin();
        $vendorRoot = base_path('plugins/integrityvendor');
        $movedVendorRoot = base_path('plugins/integrityvendor-target-'.bin2hex(random_bytes(4)));

        $this->assertTrue(rename($vendorRoot, $movedVendorRoot));
        $this->assertTrue(symlink($movedVendorRoot, $vendorRoot));

        try {
            $this->expectException(RuntimeException::class);
            app(PluginIntegrityVerifier::class)->verify($plugin);
        } finally {
            if (is_link($vendorRoot)) {
                unlink($vendorRoot);
            }
            if (is_dir($movedVendorRoot)) {
                rename($movedVendorRoot, $vendorRoot);
            }
        }
    }

    public function test_rejects_registry_path_that_does_not_match_plugin_identity(): void
    {
        $plugin = $this->registeredPlugin();
        $plugin->package_path = 'plugins/other/integrity-check/1.0.0';
        $plugin->save();

        $this->expectException(RuntimeException::class);
        app(PluginIntegrityVerifier::class)->verify($plugin);
    }

    public function test_rejects_symlinked_plugin_file_when_supported(): void
    {
        $plugin = $this->registeredPlugin();
        $target = storage_path('framework/testing/plugin-integrity-target-'.bin2hex(random_bytes(4)).'.php');
        file_put_contents($target, '<?php return true;');
        $link = $this->absolutePath.'/linked.php';

        if (! @symlink($target, $link)) {
            unlink($target);
            $this->markTestSkipped('Symlinks are not available in this test environment.');
        }

        try {
            $this->expectException(RuntimeException::class);
            app(PluginIntegrityVerifier::class)->verify($plugin);
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
