<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PluginSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    private string $archivePath;
    private array $keyPair;
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The sodium extension is required.');
        }

        $stagingDirectory = base_path('plugins/.staging');
        if (! is_dir($stagingDirectory)) {
            mkdir($stagingDirectory, 0700, true);
        }

        $this->archivePath = $stagingDirectory.'/signature-test-'.bin2hex(random_bytes(6)).'.zip';
        file_put_contents($this->archivePath, "test plugin archive\n");

        $this->keyPair = sodium_crypto_sign_keypair();
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

        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
        ]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->archivePath) && is_file($this->archivePath)) {
            unlink($this->archivePath);
        }

        parent::tearDown();
    }

    private function registeredPlugin(): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $this->manifest['key'],
            'name' => $this->manifest['name'],
            'version' => $this->manifest['version'],
            'type' => $this->manifest['type'],
            'status' => 'staged',
            'manifest' => $this->manifest,
        ])->save();

        return $plugin;
    }

    private function signedPackage(): string
    {
        $checksum = hash_file('sha256', $this->archivePath);
        $payload = PluginSignatureVerifier::payloadForSignature($this->manifest, $checksum);

        return base64_encode(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($this->keyPair)));
    }

    public function test_valid_trusted_signature_records_package_identity_atomically(): void
    {
        $plugin = $this->registeredPlugin();

        $verified = app(PluginSignatureVerifier::class)->verifyAndRecord(
            $plugin, $this->manifest, $this->archivePath, $this->signedPackage(),
            'test-release-key', 'plugins/aec/course-management/1.0.0',
        );

        $this->assertSame('staged', $verified->status);
        $this->assertSame(hash_file('sha256', $this->archivePath), $verified->package_sha256);
        $this->assertSame('test-release-key', $verified->signature_key_id);
        $this->assertNotNull($verified->signature_verified_at);
        $this->assertSame('plugins/aec/course-management/1.0.0', $verified->package_path);
    }

    public function test_invalid_signature_is_rejected_without_recording_trust(): void
    {
        $plugin = $this->registeredPlugin();

        try {
            app(PluginSignatureVerifier::class)->verifyAndRecord(
                $plugin, $this->manifest, $this->archivePath,
                base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES)),
                'test-release-key', 'plugins/aec/course-management/1.0.0',
            );
            $this->fail('An invalid signature must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('signature is invalid', $exception->getMessage());
        }

        $this->assertNull($plugin->fresh()->signature_verified_at);
    }

    public function test_untrusted_key_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(PluginSignatureVerifier::class)->verifyAndRecord(
            $this->registeredPlugin(), $this->manifest, $this->archivePath,
            $this->signedPackage(), 'unknown-key', 'plugins/aec/course-management/1.0.0',
        );
    }

    public function test_archive_outside_private_staging_directory_is_rejected(): void
    {
        $outside = storage_path('app/plugin-outside-test.zip');
        if (! is_dir(dirname($outside))) {
            mkdir(dirname($outside), 0700, true);
        }
        file_put_contents($outside, 'not in plugin staging');

        try {
            $this->expectException(\InvalidArgumentException::class);
            app(PluginSignatureVerifier::class)->verifyAndRecord(
                $this->registeredPlugin(), $this->manifest, $outside,
                $this->signedPackage(), 'test-release-key', 'plugins/aec/course-management/1.0.0',
            );
        } finally {
            if (is_file($outside)) {
                unlink($outside);
            }
        }
    }

    public function test_unsafe_destination_path_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PluginSignatureVerifier::class)->verifyAndRecord(
            $this->registeredPlugin(), $this->manifest, $this->archivePath,
            $this->signedPackage(), 'test-release-key', 'plugins/aec/../escape/1.0.0',
        );
    }

    public function test_manifest_identity_must_match_registry_record(): void
    {
        $plugin = $this->registeredPlugin();
        $wrongManifest = $this->manifest;
        $wrongManifest['name'] = 'Impersonated Plugin';

        $this->expectException(\InvalidArgumentException::class);
        app(PluginSignatureVerifier::class)->verifyAndRecord(
            $plugin, $wrongManifest, $this->archivePath,
            $this->signedPackage(), 'test-release-key', 'plugins/aec/course-management/1.0.0',
        );
    }

    public function test_unvalidated_registry_record_cannot_receive_verified_metadata(): void
    {
        $plugin = $this->registeredPlugin();
        $plugin->status = 'discovered';
        $plugin->save();

        $this->expectException(RuntimeException::class);
        app(PluginSignatureVerifier::class)->verifyAndRecord(
            $plugin, $this->manifest, $this->archivePath,
            $this->signedPackage(), 'test-release-key', 'plugins/aec/course-management/1.0.0',
        );
    }
}
