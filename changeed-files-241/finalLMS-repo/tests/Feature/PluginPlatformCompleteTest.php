<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginCompatibilityChecker;
use App\Services\Plugins\PluginContext;
use App\Services\Plugins\PluginHookRegistry;
use App\Services\Plugins\PluginRuntime;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use ZipArchive;

class PluginPlatformCompleteTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPair;
    private array $cleanupDirs = [];
    private array $cleanupFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class) || ! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The PHP zip and sodium extensions are required.');
        }

        $this->keyPair = sodium_crypto_sign_keypair();
        config([
            'plugins.trusted_public_keys' => [
                'platform-test-key' => base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        foreach ($this->cleanupDirs as $dir) {
            if (is_dir($dir) && ! is_link($dir)) {
                File::deleteDirectory($dir);
            }
        }
        app(PluginHookRegistry::class)->reset();

        parent::tearDown();
    }

    private function makeSignedPackage(array $manifest, string $entrypointCode): array
    {
        $tmpDir = storage_path('framework/testing');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }
        $archivePath = $tmpDir.'/plugin-platform-'.bin2hex(random_bytes(6)).'.zip';
        $this->cleanupFiles[] = $archivePath;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $this->assertTrue($zip->addFromString('src/Plugin.php', $entrypointCode));
        $this->assertTrue($zip->addFromString('plugin.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
        $this->assertTrue($zip->close());

        $bytes = file_get_contents($archivePath);
        $checksum = hash('sha256', $bytes);
        $payload = PluginSignatureVerifier::payloadForSignature($manifest, $checksum);
        $signature = base64_encode(sodium_crypto_sign_detached(
            $payload,
            sodium_crypto_sign_secretkey($this->keyPair),
        ));

        return [
            'package' => UploadedFile::fake()->createWithContent('plugin-package.zip', $bytes),
            'manifest' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'signature' => $signature,
            'key_id' => 'platform-test-key',
        ];
    }

    private function baseManifest(array $overrides = []): array
    {
        return array_merge([
            'schema_version' => 1,
            'key' => 'aec.course-studio-test',
            'name' => 'Course Studio Test',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => ['admin', 'branch', 'teacher', 'student', 'guardian'],
            'permissions' => ['studio.view', 'studio.manage'],
            'capabilities' => ['course-studio', 'portal-widgets'],
        ], $overrides);
    }

    public function test_admin_can_discover_private_packages_without_activating_or_executing_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $packageDir = base_path('plugins/test/discovered-sample/1.0.0');
        $this->cleanupDirs[] = base_path('plugins/test/discovered-sample');

        mkdir($packageDir.'/src', 0750, true);
        file_put_contents($packageDir.'/src/Plugin.php', '<?php file_put_contents(storage_path("discovered-executed"), "yes");');
        file_put_contents($packageDir.'/plugin.json', json_encode([
            'schema_version' => 1,
            'key' => 'test.discovered-sample',
            'name' => 'Discovered Sample',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => ['admin'],
            'permissions' => ['sample.use'],
            'capabilities' => ['sample'],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->post(route('admin.plugins.discover'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'test.discovered-sample',
            'status' => 'discovered',
            'activated_at' => null,
        ]);
        $this->assertFalse(file_exists(storage_path('discovered-executed')));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.plugins.discovered',
        ]);
    }

    public function test_compatibility_checker_blocks_incompatible_php_laravel_and_lms_versions(): void
    {
        $checker = app(PluginCompatibilityChecker::class);

        $ok = $checker->check($this->baseManifest());
        $this->assertTrue($ok['compatible']);

        $badPhp = $checker->check($this->baseManifest([
            'requires' => ['php' => '^99.0', 'laravel' => '^12.0', 'lms' => '^1.0'],
        ]));
        $this->assertFalse($badPhp['compatible']);

        config(['plugins.lms_version' => '1.0.0']);
        $badLms = $checker->check($this->baseManifest([
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '>=2.0.0'],
        ]));
        $this->assertFalse($badLms['compatible']);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $manifest = $this->baseManifest([
            'key' => 'test.incompatible-pkg',
            'requires' => ['php' => '^99.0', 'laravel' => '^12.0', 'lms' => '^1.0'],
        ]);
        $upload = $this->makeSignedPackage($manifest, '<?php return new class implements \\App\\Contracts\\Plugins\\PluginContract { public function register(\\App\\Services\\Plugins\\PluginContext $c): void {} public function boot(\\App\\Services\\Plugins\\PluginContext $c): void {} };');

        $this->actingAs($admin)->post(route('admin.plugins.install'), $upload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('plugin_registry', [
            'plugin_key' => 'test.incompatible-pkg',
            'status' => 'installed',
        ]);
    }

    public function test_signed_upgrade_tracks_version_history_and_rejects_downgrades(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->cleanupDirs[] = base_path('plugins/aec/course-studio-test');

        $entrypoint = <<<'PHP'
<?php
return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void {}
    public function boot(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;

        $v1Manifest = $this->baseManifest(['version' => '1.0.0']);
        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->makeSignedPackage($v1Manifest, $entrypoint))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $this->actingAs($admin)->post(route('admin.plugins.activate', 'aec.course-studio-test'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        // Downgrade / equal version must be rejected cleanly
        $this->actingAs($admin)->post(
            route('admin.plugins.update', 'aec.course-studio-test'),
            $this->makeSignedPackage($v1Manifest, $entrypoint),
        )->assertRedirect()->assertSessionHas('error');

        // Valid signed upgrade 1.0.0 -> 1.1.0 succeeds via update_staged -> active
        $v2Manifest = $this->baseManifest(['version' => '1.1.0']);
        $this->actingAs($admin)->post(
            route('admin.plugins.update', 'aec.course-studio-test'),
            $this->makeSignedPackage($v2Manifest, $entrypoint),
        )->assertRedirect(route('admin.plugins.index'))->assertSessionHas('success');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-studio-test')->firstOrFail();
        $this->assertSame('1.1.0', $plugin->version);
        $this->assertSame('active', $plugin->status);
        $this->assertSame('healthy', $plugin->health_status);
        $this->assertSame('plugins/aec/course-studio-test/1.1.0', $plugin->package_path);
        $this->assertCount(1, $plugin->metadata['version_history'] ?? []);
        $this->assertSame('1.0.0', $plugin->metadata['version_history'][0]['version']);
        $this->assertFileExists(base_path('plugins/aec/course-studio-test/1.1.0/src/Plugin.php'));
    }

    public function test_health_check_and_sanitized_export_report_integrity_and_retention(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->cleanupDirs[] = base_path('plugins/aec/course-studio-test');

        $entrypoint = '<?php return new class implements \\App\\Contracts\\Plugins\\PluginContract { public function register(\\App\\Services\\Plugins\\PluginContext $c): void {} public function boot(\\App\\Services\\Plugins\\PluginContext $c): void {} };';
        $manifest = $this->baseManifest(['version' => '1.0.0']);

        $this->actingAs($admin)->post(route('admin.plugins.install'), $this->makeSignedPackage($manifest, $entrypoint))
            ->assertRedirect(route('admin.plugins.index'));

        $this->actingAs($admin)->post(route('admin.plugins.health', 'aec.course-studio-test'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('success');

        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.course-studio-test')->firstOrFail();
        $this->assertSame('healthy', $plugin->health_status);

        $exportResponse = $this->actingAs($admin)->get(route('admin.plugins.export', 'aec.course-studio-test'));
        $exportResponse->assertOk()
            ->assertJsonPath('plugin.key', 'aec.course-studio-test')
            ->assertJsonPath('plugin.version', '1.0.0')
            ->assertJsonPath('manifest.key', 'aec.course-studio-test');
        $this->assertStringNotContainsString(
            base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
            $exportResponse->getContent(),
        );

        // Tamper installed file -> health check marks failed
        file_put_contents(base_path('plugins/aec/course-studio-test/1.0.0/src/Plugin.php'), '<?php // tampered');
        $this->actingAs($admin)->post(route('admin.plugins.health', 'aec.course-studio-test'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('error');

        $this->assertSame('failed', $plugin->fresh()->health_status);
    }

    public function test_hook_registry_and_five_portal_navigation_stop_immediately_on_deactivation(): void
    {
        $packagePath = 'plugins/test/multiportal-hook/1.0.0';
        $absolute = base_path($packagePath);
        $this->cleanupDirs[] = base_path('plugins/test/multiportal-hook');
        mkdir($absolute.'/src', 0750, true);

        $code = <<<'PHP'
<?php
return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void
    {
        foreach (['admin', 'branch', 'teacher', 'student', 'guardian'] as $portal) {
            $context->registerPortalNavigation($portal, 'Studio '.ucfirst($portal), 'dashboard', ['icon' => '★']);
        }
        $context->registerHook('lms.dashboard.widgets', fn (array $payload) => 'widget-for-'.($payload['role'] ?? 'guest'), 5);
    }
    public function boot(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;
        $entrypoint = $absolute.'/src/Plugin.php';
        file_put_contents($entrypoint, $code);

        $manifest = $this->baseManifest([
            'key' => 'test.multiportal-hook',
            'name' => 'Multiportal Hook',
        ]);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.multiportal-hook',
            'name' => 'Multiportal Hook',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => $manifest,
            'package_sha256' => hash('sha256', 'signed-archive'),
            'signature_key_id' => 'platform-test-key',
            'signature_verified_at' => now(),
            'package_path' => $packagePath,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'src/Plugin.php' => hash_file('sha256', $entrypoint),
                ],
            ],
        ])->save();

        $runtime = app(PluginRuntime::class);
        $runtime->registerActivePlugins();
        $runtime->bootActivePlugins();

        $hooks = app(PluginHookRegistry::class);
        $this->assertTrue($hooks->hasCapability('course-studio'));
        $this->assertSame(['widget-for-teacher'], $hooks->dispatch('lms.dashboard.widgets', ['role' => 'teacher']));

        foreach (['admin', 'branch', 'teacher', 'student', 'guardian'] as $portal) {
            $items = $hooks->portalNavigation($portal);
            $this->assertCount(1, $items, "Expected nav item for {$portal}");
        }

        // Deactivate -> hook dispatch and all 5 portal links drop immediately
        PluginInstallation::query()->where('plugin_key', 'test.multiportal-hook')->update(['status' => 'inactive']);
        $this->assertFalse($hooks->hasCapability('course-studio'));
        $this->assertSame([], $hooks->dispatch('lms.dashboard.widgets', ['role' => 'teacher']));
        foreach (['admin', 'branch', 'teacher', 'student', 'guardian'] as $portal) {
            $this->assertSame([], $hooks->portalNavigation($portal));
        }
    }

    public function test_non_admin_roles_cannot_trigger_discovery_updates_health_or_export(): void
    {
        foreach (['branch_manager', 'teacher', 'student', 'guardian'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
            $this->actingAs($user)->post(route('admin.plugins.discover'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.plugins.update', 'aec.sample'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.plugins.health', 'aec.sample'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.plugins.export', 'aec.sample'))->assertForbidden();
        }
    }
}
