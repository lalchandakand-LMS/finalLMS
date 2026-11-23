<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\PluginInstallation;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Plugins\PluginRuntime;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PaymentGatewayPluginRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath = 'plugins/test/payment-gateway/1.0.0';
    private string $keyPair;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPair = sodium_crypto_sign_keypair();
        config(['plugins.trusted_public_keys' => [
            'payment-test-key' => base64_encode(sodium_crypto_sign_publickey($this->keyPair)),
        ]]);
    }

    protected function tearDown(): void
    {
        $path = base_path($this->packagePath);
        if (is_dir($path) && ! is_link($path)) {
            File::deleteDirectory($path);
        }
        parent::tearDown();
    }

    private function installSignedFixture(array $capabilities = ['payments.gateway']): PluginInstallation
    {
        $directory = base_path($this->packagePath.'/src');
        File::ensureDirectoryExists($directory);
        $entrypoint = <<<'PHP'
<?php
return new class implements \App\Contracts\Plugins\PluginContract {
    public function register(\App\Services\Plugins\PluginContext $context): void
    {
        $context->registerPaymentGateway('fixture_gateway', \Tests\Feature\TestPaymentGatewayAdapter::class);
    }
    public function boot(\App\Services\Plugins\PluginContext $context): void {}
};
PHP;
        $file = $directory.'/Plugin.php';
        file_put_contents($file, $entrypoint);
        $manifest = [
            'schema_version' => 1,
            'key' => 'test.payment-gateway',
            'name' => 'Payment Gateway Fixture',
            'version' => '1.0.0',
            'type' => 'provider',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => [],
            'conflicts' => [],
            'portals' => [],
            'permissions' => [],
            'capabilities' => $capabilities,
        ];
        $checksum = hash('sha256', 'signed payment gateway fixture');
        $signature = base64_encode(sodium_crypto_sign_detached(
            PluginSignatureVerifier::payloadForSignature($manifest, $checksum),
            sodium_crypto_sign_secretkey($this->keyPair),
        ));
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $manifest['key'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'type' => $manifest['type'],
            'status' => 'active',
            'manifest' => $manifest,
            'package_sha256' => $checksum,
            'signature_key_id' => 'payment-test-key',
            'signature_verified_at' => now(),
            'package_path' => $this->packagePath,
            'metadata' => [
                'granted_permissions' => [],
                'integrity_version' => 1,
                'release_signature' => $signature,
                'signature_record_version' => 1,
                'installed_file_hashes' => ['src/Plugin.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        return $plugin;
    }

    public function test_active_signed_gateway_plugin_registers_its_adapter_with_core_catalog(): void
    {
        $this->installSignedFixture();

        app(PluginRuntime::class)->registerActivePlugins();

        $manager = app(PaymentGatewayManager::class);
        $this->assertContains('fixture_gateway', $manager->installedKeys());
        $this->assertSame('test.payment-gateway', $manager->catalog()['fixture_gateway']['plugin_key']);
        $this->assertTrue($manager->catalog()['fixture_gateway']['configured']);
        $this->assertSame('Fixture Gateway', $manager->catalog()['fixture_gateway']['name']);
    }

    public function test_real_universal_payment_package_manifest_and_entrypoint_register_bkash_via_verified_runtime(): void
    {
        $packagePath = base_path('plugins/aec/universal-payment/1.0.0');
        $manifest = json_decode((string) file_get_contents($packagePath.'/plugin.json'), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame([], app(\\App\\Services\\Plugins\\PluginManifestValidator::class)->errors($manifest));

        $files = [];
        $iterator = new \\RecursiveIteratorIterator(new \\RecursiveDirectoryIterator($packagePath, \\FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($packagePath) + 1));
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        $checksum = hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
        $signature = base64_encode(sodium_crypto_sign_detached(
            PluginSignatureVerifier::payloadForSignature($manifest, $checksum),
            sodium_crypto_sign_secretkey($this->keyPair),
        ));
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $manifest['key'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'type' => $manifest['type'],
            'status' => 'active',
            'manifest' => $manifest,
            'package_sha256' => $checksum,
            'signature_key_id' => 'payment-test-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/universal-payment/1.0.0',
            'metadata' => [
                'granted_permissions' => [],
                'integrity_version' => 1,
                'release_signature' => $signature,
                'signature_record_version' => 1,
                'installed_file_hashes' => $files,
            ],
        ])->save();

        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);

        app(PluginRuntime::class)->registerActivePlugins();

        $manager = app(PaymentGatewayManager::class);
        $this->assertSame('active', $plugin->fresh()->status);
        $this->assertContains('bkash', $manager->installedKeys());
        $this->assertSame('aec.universal-payment', $manager->catalog()['bkash']['plugin_key']);
        $this->assertTrue($manager->catalog()['bkash']['configured']);
    }


    public function test_plugin_without_payments_gateway_capability_cannot_register_adapter(): void
    {
        $plugin = $this->installSignedFixture(['read-only']);
        app(PluginRuntime::class)->registerActivePlugins();

        $this->assertSame('failed', $plugin->fresh()->status);
        $this->assertNotContains('fixture_gateway', app(PaymentGatewayManager::class)->installedKeys());
    }

    public function test_deactivated_gateway_plugin_is_removed_from_catalog_and_cannot_be_resolved(): void
    {
        $plugin = $this->installSignedFixture();
        app(PluginRuntime::class)->registerActivePlugins();
        $manager = app(PaymentGatewayManager::class);

        $this->assertArrayHasKey('fixture_gateway', $manager->catalog());
        $plugin->update(['status' => 'inactive']);

        $this->assertArrayNotHasKey('fixture_gateway', $manager->catalog());
        $this->expectException(\InvalidArgumentException::class);
        $manager->driver('fixture_gateway');
    }
}

class TestPaymentGatewayAdapter implements PaymentGateway
{
    public function key(): string { return 'fixture_gateway'; }
    public function label(): string { return 'Fixture Gateway'; }
    public function supportedCurrencies(): array { return ['BDT']; }
    public function isConfigured(): bool { return true; }
    public function createCheckout(Payment $payment, string $callbackUrl, string $payerReference): array
    {
        return ['payment_id' => 'fixture-payment', 'checkout_url' => 'https://payments.example.test/checkout'];
    }
    public function callbackPaymentId(array $callback): ?string { return $callback['paymentID'] ?? null; }
    public function verifyCallback(Payment $payment, array $callback): array
    {
        return ['verified' => [], 'executed' => []];
    }
}
