<?php

namespace Tests\Feature;

use App\Models\SystemUpdateCheck;
use App\Models\User;
use App\Services\ReleaseManifestChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AdminUpdateCenterTest extends TestCase
{
    use RefreshDatabase;

    private ?string $publicKeyPath = null;

    protected function tearDown(): void
    {
        if ($this->publicKeyPath && is_file($this->publicKeyPath)) {
            @unlink($this->publicKeyPath);
        }

        parent::tearDown();
    }

    public function test_admin_can_open_the_system_update_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('System Updates')
            ->assertSee('Check for updates');
    }

    public function test_non_admin_roles_cannot_open_or_check_system_updates(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.updates.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.updates.check'))
            ->assertForbidden();

        $this->assertDatabaseCount('system_update_checks', 0);
    }

    public function test_disabled_update_check_is_recorded_without_downloading_any_package(): void
    {
        config(['services.lms_updates.enabled' => false]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.check'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('system_update_checks', [
            'checked_by' => $admin->id,
            'status' => 'failed',
            'current_version' => config('services.lms_updates.current_version', '0.0.0'),
        ]);
    }

    public function test_admin_check_accepts_a_valid_signed_manifest_and_persists_its_digest(): void
    {
        $this->configureSignedRelease();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.check'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $check = SystemUpdateCheck::query()->firstOrFail();
        $this->assertSame('available', $check->status);
        $this->assertSame('1.0.1', $check->target_version);
        $this->assertSame(64, strlen($check->manifest_sha256));
        $this->assertSame(40, strlen($check->git_commit));
        $this->assertSame('1.0.1', $check->details['version']);
    }

    public function test_release_manifest_with_invalid_signature_is_rejected(): void
    {
        $this->configureSignedRelease(signatureOverride: 'not-a-valid-rsa-signature');

        try {
            app(ReleaseManifestChecker::class)->check();
            $this->fail('An invalid release signature must never be accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('signature is invalid', $exception->getMessage());
        }
    }

    public function test_release_manifest_redirect_to_untrusted_host_is_rejected(): void
    {
        $this->configureSignedRelease();
        Http::fake([
            'https://github.com/lalchandakand-LMS/finalLMS/releases/latest/download/release-manifest.json' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/private',
            ]),
        ]);

        try {
            app(ReleaseManifestChecker::class)->check();
            $this->fail('A redirect to an untrusted host must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('untrusted destination', $exception->getMessage());
        }
    }

    private function configureSignedRelease(?string $signatureOverride = null): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 3072,
        ]);
        $this->assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $publicPem = $details['key'];
        $this->publicKeyPath = tempnam(sys_get_temp_dir(), 'aloms-release-key-');
        $this->assertNotFalse($this->publicKeyPath);
        file_put_contents($this->publicKeyPath, $publicPem);

        $derBase64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $publicPem);
        $der = base64_decode((string) $derBase64, true);
        $fingerprint = hash('sha256', $der);

        $manifest = [
            'product' => 'aloms-english-center-lms',
            'version' => '1.0.1',
            'release_channel' => 'stable',
            'git_commit' => str_repeat('a', 40),
            'minimum_php' => '8.3.0',
            'laravel_major' => 12,
            'required_extensions' => ['openssl', 'pdo_mysql'],
            'package_file' => 'AlomsEnglishCenter-cpanel.zip',
            'package_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => str_repeat('c', 64),
            'signing_public_key_sha256' => $fingerprint,
            'published_at' => '2026-11-10T00:00:00Z',
            'artifact_url' => 'https://github.com/lalchandakand-LMS/finalLMS/releases/download/v1.0.1/AlomsEnglishCenter-cpanel.zip',
        ];
        $manifestBytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        openssl_sign($manifestBytes, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $manifestUrl = 'https://github.com/lalchandakand-LMS/finalLMS/releases/latest/download/release-manifest.json';
        $signatureUrl = 'https://github.com/lalchandakand-LMS/finalLMS/releases/latest/download/release-manifest.sig';
        Http::fake([
            $manifestUrl => Http::response($manifestBytes, 200),
            $signatureUrl => Http::response($signatureOverride ?? $signature, 200),
        ]);

        config([
            'services.lms_updates.enabled' => true,
            'services.lms_updates.manifest_url' => $manifestUrl,
            'services.lms_updates.public_key_path' => $this->publicKeyPath,
            'services.lms_updates.channel' => 'stable',
            'services.lms_updates.current_version' => '1.0.0',
        ]);
    }
}
