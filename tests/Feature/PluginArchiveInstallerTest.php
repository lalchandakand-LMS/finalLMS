<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginArchiveInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PluginArchiveInstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $archivePath;
    private string $finalPath;
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The PHP zip extension is required.');
        }

        $staging = base_path('plugins/.staging');
        if (! is_dir($staging)) {
            mkdir($staging, 0700, true);
        }

        $this->archivePath = $staging.'/installer-test-'.bin2hex(random_bytes(6)).'.zip';
        $this->finalPath = base_path('plugins/aec/course-management/1.0.0');

        $this->manifest = [
            'schema_version' => 1,
            'key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => ['admin', 'teacher', 'student'],
            'permissions' => ['courses.manage'],
            'capabilities' => ['courses'],
        ];

        config([
            'plugins.trusted_public_keys' => ['test-release-key' => 'test-public-key-placeholder'],
            'plugins.max_package_bytes' => 52428800,
            'plugins.max_archive_entries' => 1000,
            'plugins.max_uncompressed_bytes' => 104857600,
            'plugins.max_file_bytes' => 20971520,
            'plugins.max_compression_ratio' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->archivePath) && is_file($this->archivePath)) {
            unlink($this->archivePath);
        }

        if (isset($this->finalPath) && is_dir($this->finalPath) && ! is_link($this->finalPath)) {
            File::deleteDirectory($this->finalPath);
        }

        parent::tearDown();
    }

    private function createArchive(array $files): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($result === true);

        foreach ($files as $path => $contents) {
            $this->assertTrue($zip->addFromString($path, $contents));
        }

        $this->assertTrue($zip->close());
    }

    private function registeredPlugin(?string $checksum = null): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $this->manifest['key'],
            'name' => $this->manifest['name'],
            'version' => $this->manifest['version'],
            'type' => $this->manifest['type'],
            'status' => 'staged',
            'manifest' => $this->manifest,
            'package_sha256' => $checksum ?? hash_file('sha256', $this->archivePath),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/course-management/1.0.0',
        ])->save();

        return $plugin;
    }

    public function test_installs_only_verified_package_into_canonical_private_directory(): void
    {
        $this->createArchive([
            'src/Plugin.php' => '<?php return new class {};',
            'resources/course.json' => '{"title":"Spoken English"}',
        ]);

        $installed = app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );

        $this->assertSame('installed', $installed->status);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
        $this->assertFileExists($this->finalPath.'/resources/course.json');
    }

    public function test_rejects_zip_slip_and_does_not_write_outside_plugin_directory(): void
    {
        $this->createArchive([
            'src/Plugin.php' => '<?php return new class {};',
            '../escaped-plugin-test.php' => '<?php echo "unsafe";',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_uncompressed_archive_size_over_limit(): void
    {
        config(['plugins.max_uncompressed_bytes' => 100]);
        $this->createArchive([
            'src/Plugin.php' => str_repeat('A', 1024),
        ]);

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_package_when_signed_entrypoint_is_missing(): void
    {
        $this->createArchive([
            'README.md' => 'No plugin entrypoint here.',
        ]);

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_checksum_mismatch_before_opening_archive(): void
    {
        $this->createArchive([
            'src/Plugin.php' => '<?php return new class {};',
        ]);

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(str_repeat('0', 64)),
            $this->archivePath,
        );
    }

    public function test_rejects_untrusted_or_revoked_release_key(): void
    {
        $this->createArchive([
            'src/Plugin.php' => '<?php return new class {};',
        ]);
        config(['plugins.trusted_public_keys' => []]);

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_invalid_zip_archive(): void
    {
        file_put_contents($this->archivePath, 'not a zip archive');

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_symbolic_link_entries(): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $this->assertTrue($zip->addFromString('src/Plugin.php', '<?php return new class {};'));
        $this->assertTrue($zip->setExternalAttributesName(
            'src/Plugin.php',
            ZipArchive::OPSYS_UNIX,
            (0120777 << 16),
        ));
        $this->assertTrue($zip->close());

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_case_colliding_archive_paths(): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $this->assertTrue($zip->addFromString('src/Plugin.php', '<?php return new class {};'));
        $this->assertTrue($zip->addFromString('SRC/plugin.php', '<?php return new class {};'));
        $this->assertTrue($zip->close());

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );
    }

    public function test_rejects_existing_installation_without_overwriting_it(): void
    {
        $this->createArchive([
            'src/Plugin.php' => '<?php return new class {};',
        ]);
        mkdir($this->finalPath, 0750, true);
        file_put_contents($this->finalPath.'/keep.txt', 'existing');

        $this->expectException(RuntimeException::class);
        app(PluginArchiveInstaller::class)->installVerifiedPackage(
            $this->registeredPlugin(),
            $this->archivePath,
        );

        $this->assertFileExists($this->finalPath.'/keep.txt');
    }
}
