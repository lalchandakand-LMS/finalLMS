<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class AdminPluginManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $archivePath;
    private string $keyPair;
    private array $manifest;
    private string $finalPath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class) || ! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The PHP zip and sodium extensions are required.');
        }

        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $this->archivePath = $directory.'/admin-plugin-'.bin2hex(random_bytes(6)).'.zip';
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

        $this->keyPair = sodium_crypto_sign_keypair();
        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
        ]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->archivePath) && is_file($this->archivePath)) {
            unlink($this->archivePath);
        }

        if (isset($this->finalPath) && is_dir($this->finalPath) && ! is_link($this->finalPath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($this->finalPath);
        }

        parent::tearDown();
    }

    private function uploadData(?string $signature = null, ?string $manifestJson = null): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $this->assertTrue($zip->addFromString('src/Plugin.php', '<?php return new class {};'));
        $this->assertTrue($zip->close());

        $bytes = file_get_contents($this->archivePath);
        $checksum = hash('sha256', $bytes);
        $payload = PluginSignatureVerifier::payloadForSignature($this->manifest, $checksum);
        $detached = $signature ?? base64_encode(sodium_crypto_sign_detached(
            $payload,
            sodium_crypto_sign_secretkey($this->keyPair),
        ));

        return [
            'package' => UploadedFile::fake()->createWithContent('course-management.zip', $bytes),
            'manifest' => $manifestJson ?? json_encode($this->manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'signature' => $detached,
            'key_id' => 'test-release-key',
        ];
    }

    public function test_admin_can_view_plugin_manager_and_install_a_signed_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('Plugin Manager')
            ->assertSee('Install a signed plugin')
            ->assertSee('Activation remains disabled');

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
            'signature_key_id' => 'test-release-key',
        ]);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.installed',
        ]);
    }

    public function test_invalid_signature_is_audited_as_failed_and_never_installed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $invalidSignature = base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES));

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData($invalidSignature))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'failed',
            'signature_verified_at' => null,
        ]);
        $this->assertDirectoryDoesNotExist($this->finalPath);
    }

    public function test_admin_can_retry_a_failed_uninstalled_plugin_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $invalidSignature = base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES));

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData($invalidSignature))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'failed',
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
        ]);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
    }

    public function test_non_admin_cannot_view_or_install_plugins(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.plugins.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.plugins.install'), [])->assertForbidden();
        $this->assertDatabaseCount('plugin_registry', 0);
    }

    public function test_duplicate_plugin_identity_cannot_be_reinstalled_or_overwritten(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $existing = new PluginInstallation();
        $existing->forceFill([
            'plugin_key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => $this->manifest,
        ])->save();

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
            'version' => '1.0.0',
        ]);
        $this->assertDirectoryDoesNotExist($this->finalPath);
    }

    public function test_invalid_manifest_json_does_not_create_a_registry_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData(
            manifestJson: '{"key":'
        ))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('plugin_registry', 0);
    }
    public function test_admin_can_restore_a_verified_uninstall_backup_from_plugin_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $directory = $this->finalPath.'/src';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $entrypoint = $directory.'/Plugin.php';
        file_put_contents($entrypoint, '<?php return new class {};');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => ['key' => 'aec.course-management', 'dependencies' => []],
            'package_path' => 'plugins/aec/course-management/1.0.0',
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $entrypoint),
                ],
            ],
        ])->save();

        $uninstalled = app(\App\Services\Plugins\PluginUninstaller::class)->uninstall($plugin);
        $this->assertSame('uninstalled', $uninstalled->status);

        $this->actingAs($admin)->post(route('admin.plugins.restore', $uninstalled), ['confirm_restore' => '1'])
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'inactive',
        ]);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.restored',
        ]);
    }


    public function test_admin_can_rollback_to_a_verified_historical_plugin_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        if (! is_dir($this->finalPath.'/src')) {
            mkdir($this->finalPath.'/src', 0750, true);
        }
        $entrypoint = $this->finalPath.'/src/Plugin.php';
        file_put_contents($entrypoint, '<?php return new class {};');
        $hash = hash_file('sha256', $entrypoint);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '2.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => ['key' => 'aec.course-management', 'dependencies' => []],
            'package_sha256' => str_repeat('c', 64),
            'signature_key_id' => 'release-key-2',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/course-management/2.0.0',
            'metadata' => ['integrity_version' => 1, 'installed_file_hashes' => ['src/Plugin.php' => str_repeat('d', 64)]],
        ])->save();

        PluginVersionRecord::query()->create([
            'plugin_key' => 'aec.course-management',
            'version' => '1.0.0',
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'release-key-1',
            'package_path' => 'plugins/aec/course-management/1.0.0',
            'status' => 'installed',
            'installed_at' => now()->subDay(),
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['src/Plugin.php' => $hash],
                'signature_verified_at' => now()->subDays(2)->toISOString(),
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.rollback', $plugin), [
            'version' => '1.0.0',
            'confirm_rollback' => '1',
        ])->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'id' => $plugin->id,
            'status' => 'inactive',
            'version' => '1.0.0',
            'package_path' => 'plugins/aec/course-management/1.0.0',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.rolled_back',
        ]);
    }

}
