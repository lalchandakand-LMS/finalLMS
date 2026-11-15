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
        $entrypoint = <<<'PHP'
<?php

return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void {}
    public function boot(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;
        $this->assertTrue($zip->addFromString('src/Plugin.php', $entrypoint));
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
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.installed',
        ]);
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
    }


    public function test_admin_can_uninstall_inactive_plugin_without_deleting_application_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->finalPath = base_path('plugins/aec/universal-ai-assistant/1.0.0');
        if (! is_dir($this->finalPath)) mkdir($this->finalPath, 0750, true);
        file_put_contents($this->finalPath.'/src.php', '<?php // signed test package');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.universal-ai-assistant',
            'name' => 'Universal AI Assistant',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'inactive',
            'manifest' => $this->manifest,
            'package_path' => 'plugins/aec/universal-ai-assistant/1.0.0',
            'signature_verified_at' => now(),
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => 'aec.universal-ai-assistant',
            'plugin_version' => '1.0.0',
            'migration' => '2026_11_15_000001_example.php',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $admin->id,
            'assistant_role' => 'admin',
            'title' => 'Keep my AI history',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.plugins.uninstall', 'aec.universal-ai-assistant'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.universal-ai-assistant',
            'status' => 'uninstalled',
        ]);
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'aec.universal-ai-assistant',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ai_assistant_conversations', ['id' => $conversationId, 'title' => 'Keep my AI history']);
        $this->assertDirectoryDoesNotExist($this->finalPath);
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
}
