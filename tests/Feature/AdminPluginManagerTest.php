<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSignatureVerifier;
use App\Services\Plugins\PluginMigrationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class AdminPluginManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $archivePath;
    private string $keyPair;
    private array $manifest;
    private string $finalPath;
    private ?string $updatedPath = null;

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

        if (is_string($this->updatedPath) && is_dir($this->updatedPath) && ! is_link($this->updatedPath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($this->updatedPath);
        }

        parent::tearDown();
    }

    private function uploadData(?string $signature = null, ?string $manifestJson = null, ?string $migrationSource = null): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $entrypoint = <<<'PHP'
<?php

return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void {}
    public function boot(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;
        $this->assertTrue($zip->addFromString('src/Plugin.php', $entrypoint));
        if ($migrationSource !== null) {
            $this->assertTrue($zip->addFromString('migrations/2026_11_15_000001_probe.php', $migrationSource));
        }
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
            ->assertSee('Only trusted, signed packages with intact installed-file hashes can be activated.');

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
            'signature_key_id' => 'test-release-key',
        ]);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.course-management',
            'action' => 'installed',
            'version' => '1.0.0',
        ]);
        $installedPlugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertNotEmpty($installedPlugin->metadata['release_signature'] ?? null);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.installed',
        ]);
    }

    public function test_admin_can_update_inactive_signed_plugin_and_retain_previous_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $this->manifest['version'] = '1.1.0';
        $this->updatedPath = base_path('plugins/aec/course-management/1.1.0');
        $this->actingAs($admin)->post(route('admin.plugins.update', 'aec.course-management'), $this->uploadData() + [
            'backup_confirmed' => '1',
        ])->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertSame('1.1.0', $plugin->version);
        $this->assertSame('installed', $plugin->status);
        $this->assertSame('plugins/aec/course-management/1.1.0', $plugin->package_path);
        $this->assertSame('1.0.0', $plugin->metadata['previous_version_snapshot']['version'] ?? null);
        $this->assertDirectoryExists($this->finalPath);
        $this->assertFileExists($this->updatedPath.'/src/Plugin.php');
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.course-management',
            'action' => 'updated',
            'version' => '1.1.0',
        ]);
    }

    public function test_admin_can_rollback_to_previous_signed_version_and_keep_both_package_directories(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $this->manifest['version'] = '1.1.0';
        $this->updatedPath = base_path('plugins/aec/course-management/1.1.0');
        $this->actingAs($admin)->post(route('admin.plugins.update', 'aec.course-management'), $this->uploadData() + [
            'backup_confirmed' => '1',
        ])->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->post(route('admin.plugins.rollback', 'aec.course-management'), [
            'confirm_rollback' => '1',
            'backup_confirmed' => '1',
        ])->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertSame('1.0.0', $plugin->version);
        $this->assertSame('inactive', $plugin->status);
        $this->assertSame('plugins/aec/course-management/1.0.0', $plugin->package_path);
        $this->assertDirectoryExists($this->finalPath);
        $this->assertFileExists($this->updatedPath.'/src/Plugin.php');
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.course-management',
            'action' => 'update_rolled_back',
            'version' => '1.0.0',
        ]);
    }

    public function test_invalid_signed_update_restores_the_previous_registry_and_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $this->manifest['version'] = '1.1.0';
        $this->updatedPath = base_path('plugins/aec/course-management/1.1.0');
        $invalidSignature = base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES));
        $this->actingAs($admin)->post(route('admin.plugins.update', 'aec.course-management'), $this->uploadData($invalidSignature) + [
            'backup_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('error');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertSame('1.0.0', $plugin->version);
        $this->assertSame('inactive', $plugin->status);
        $this->assertSame('plugins/aec/course-management/1.0.0', $plugin->package_path);
        $this->assertDirectoryExists($this->finalPath);
        $this->assertDirectoryDoesNotExist($this->updatedPath);
    }

    public function test_rollback_is_blocked_when_new_version_migration_refuses_to_drop_retained_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $this->manifest['version'] = '1.1.0';
        $this->manifest['migrations'] = 'migrations';
        $this->updatedPath = base_path('plugins/aec/course-management/1.1.0');
        $migration = <<<'PHP'
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'plugin_update_retained_data',
            'value' => 'preserve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new \\RuntimeException('Retained data prevents rollback.');
    }
};
PHP;
        $this->actingAs($admin)->post(route('admin.plugins.update', 'aec.course-management'), $this->uploadData(migrationSource: $migration) + [
            'backup_confirmed' => '1',
        ])->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->post(route('admin.plugins.rollback', 'aec.course-management'), [
            'confirm_rollback' => '1',
            'backup_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('error');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertSame('1.1.0', $plugin->version);
        $this->assertSame('inactive', $plugin->status);
        $this->assertDatabaseHas('settings', ['key' => 'plugin_update_retained_data', 'value' => 'preserve']);
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'aec.course-management',
            'plugin_version' => '1.1.0',
            'migration' => '2026_11_15_000001_probe.php',
            'status' => 'completed',
        ]);
    }

    public function test_admin_can_grant_only_permissions_declared_by_the_signed_manifest(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->put(route('admin.plugins.permissions', 'aec.course-management'), [
            'permissions' => ['courses.manage'],
        ])->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $this->assertSame(['courses.manage'], $plugin->metadata['granted_permissions']);

        $this->actingAs($admin)->put(route('admin.plugins.permissions', 'aec.course-management'), [
            'permissions' => ['system.superuser'],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(['courses.manage'], $plugin->fresh()->metadata['granted_permissions']);
    }

    public function test_admin_can_activate_and_deactivate_an_intact_signed_plugin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'inactive',
        ]);
        $this->assertFileExists($this->finalPath.'/src/Plugin.php');
    }

    public function test_plugin_migrations_run_before_activation_and_failure_keeps_plugin_inactive(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        $runner = $this->mock(PluginMigrationRunner::class);
        $runner->shouldReceive('migrate')->once()->andThrow(new \RuntimeException('Migration failure'));

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
        ]);
    }

    public function test_signed_plugin_migrations_complete_during_activation_after_install(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->manifest['migrations'] = 'migrations';
        $migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'plugin_activation_probe',
            'value' => 'ran',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'plugin_activation_probe')->delete();
    }
};
PHP;

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData(migrationSource: $migration))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        // Installation verifies and stages the signed package; data migrations run only on activation.
        $this->assertDatabaseMissing('settings', ['key' => 'plugin_activation_probe']);
        $this->assertDatabaseMissing('plugin_migrations', [
            'plugin_key' => 'aec.course-management',
            'migration' => '2026_11_15_000001_probe.php',
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'plugin_activation_probe', 'value' => 'ran']);
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'aec.course-management',
            'plugin_version' => '1.0.0',
            'migration' => '2026_11_15_000001_probe.php',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'active',
        ]);
        $this->assertSame(1, DB::table('plugin_migrations')
            ->where('plugin_key', 'aec.course-management')
            ->where('migration', '2026_11_15_000001_probe.php')
            ->count());
    }


    public function test_failed_signed_plugin_migration_blocks_activation_and_records_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->manifest['migrations'] = 'migrations';
        $migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        throw new \RuntimeException('Deliberate migration test failure.');
    }

    public function down(): void {}
};
PHP;

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData(migrationSource: $migration))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'aec.course-management',
            'migration' => '2026_11_15_000001_probe.php',
            'status' => 'failed',
        ]);
        // A failed activation leaves the verified package installed but inactive.
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
        ]);
        $this->assertDirectoryExists($this->finalPath);
    }


    public function test_activation_rejects_an_untrusted_release_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        config(['plugins.trusted_public_keys' => []]);

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'installed',
        ]);
    }

    public function test_non_admin_cannot_activate_or_deactivate_plugins(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->post(route('admin.plugins.activate', 'aec.course-management'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.plugins.deactivate', 'aec.course-management'))->assertForbidden();
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

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_version_history', ['plugin_key' => 'aec.course-management', 'action' => 'activated']);
        $this->assertDatabaseHas('plugin_version_history', ['plugin_key' => 'aec.course-management', 'action' => 'deactivated']);
    }


    public function test_admin_can_run_plugin_health_check_and_record_version_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->post(route('admin.plugins.health-check', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'health_status' => 'healthy',
        ]);
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.course-management',
            'action' => 'health_check_passed',
        ]);

        $this->actingAs($admin)->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('Version history')
            ->assertSee('Run health check')
            ->assertSee('Healthy');
    }


    public function test_successful_health_check_recovers_a_failed_plugin_to_inactive(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));

        app(\App\Services\Plugins\PluginLifecycle::class)->transition('aec.course-management', 'failed', [
            'health_status' => 'failed',
            'health_message' => 'A prior runtime check failed.',
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.health-check', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'inactive',
            'health_status' => 'healthy',
        ]);
    }

    public function test_health_check_failure_marks_inactive_plugin_unhealthy_when_signature_is_tampered(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->uploadData())
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));
        $this->actingAs($admin)->post(route('admin.plugins.deactivate', 'aec.course-management'))
            ->assertRedirect(route('admin.plugins.index'));

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-management')->firstOrFail();
        $metadata = $plugin->metadata ?? [];
        $metadata['release_signature'] = base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES));
        $plugin->forceFill(['metadata' => $metadata])->save();

        $this->actingAs($admin)->post(route('admin.plugins.health-check', 'aec.course-management'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'inactive',
            'health_status' => 'failed',
        ]);
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.course-management',
            'action' => 'health_check_failed',
        ]);
    }


    public function test_admin_can_uninstall_inactive_plugin_without_deleting_application_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->finalPath = base_path('plugins/test/universal-ai-assistant/1.0.0');
        if (! is_dir($this->finalPath)) mkdir($this->finalPath, 0750, true);
        file_put_contents($this->finalPath.'/src.php', '<?php // signed test package');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.universal-ai-assistant',
            'name' => 'Universal AI Assistant',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => $this->manifest,
            'package_path' => 'plugins/test/universal-ai-assistant/1.0.0',
            'signature_verified_at' => now(),
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => 'test.universal-ai-assistant',
            'plugin_version' => '1.0.0',
            'migration' => '2026_11_15_000001_example.php',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.uninstall', 'test.universal-ai-assistant'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'test.universal-ai-assistant',
            'status' => 'uninstalled',
        ]);
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.universal-ai-assistant',
            'status' => 'completed',
        ]);
        $this->assertDirectoryDoesNotExist($this->finalPath);
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'test.universal-ai-assistant',
            'action' => 'uninstalled',
        ]);
    }

    public function test_non_admin_cannot_view_or_install_plugins(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.plugins.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.plugins.install'), [])->assertForbidden();
        $this->actingAs($student)->post(route('admin.plugins.health-check', 'aec.course-management'))->assertForbidden();
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
}
