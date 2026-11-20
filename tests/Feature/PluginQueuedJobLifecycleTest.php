<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePluginJobActive;
use App\Models\PluginInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginQueuedJobLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function signedPlugin(string $status = 'active', array $grants = ['notifications.send']): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.test-notifier',
            'name' => 'Test Notifier',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => [
                'schema_version' => 1,
                'key' => 'aec.test-notifier',
                'name' => 'Test Notifier',
                'version' => '1.0.0',
                'type' => 'module',
                'entrypoint' => 'src/Plugin.php',
                'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
                'portals' => ['admin'],
                'permissions' => ['notifications.send'],
            ],
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/test-notifier/1.0.0',
            'metadata' => ['granted_permissions' => $grants],
        ])->save();

        return $plugin;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['plugins.trusted_public_keys' => [
            'test-release-key' => base64_encode(random_bytes(32)),
        ]]);
    }

    public function test_active_signed_plugin_with_admin_granted_permission_can_run_queued_work(): void
    {
        $this->signedPlugin();
        $called = false;
        $result = (new EnsurePluginJobActive('aec.test-notifier', 'notifications.send'))
            ->handle(new \stdClass(), function () use (&$called): string {
                $called = true;

                return 'executed';
            });

        $this->assertTrue($called);
        $this->assertSame('executed', $result);
    }

    public function test_deactivated_plugin_queued_work_is_dropped(): void
    {
        $this->signedPlugin('inactive');
        $called = false;
        $result = (new EnsurePluginJobActive('aec.test-notifier', 'notifications.send'))
            ->handle(new \stdClass(), function () use (&$called): string {
                $called = true;

                return 'executed';
            });

        $this->assertFalse($called);
        $this->assertNull($result);
    }

    public function test_revoked_permission_drops_already_queued_work(): void
    {
        $this->signedPlugin('active', []);
        $called = false;
        $result = (new EnsurePluginJobActive('aec.test-notifier', 'notifications.send'))
            ->handle(new \stdClass(), function () use (&$called): string {
                $called = true;

                return 'executed';
            });

        $this->assertFalse($called);
        $this->assertNull($result);
    }

    public function test_missing_or_untrusted_release_key_drops_queued_work(): void
    {
        $this->signedPlugin();
        config(['plugins.trusted_public_keys' => ['test-release-key' => '']]);
        $called = false;
        $result = (new EnsurePluginJobActive('aec.test-notifier', 'notifications.send'))
            ->handle(new \stdClass(), function () use (&$called): string {
                $called = true;

                return 'executed';
            });

        $this->assertFalse($called);
        $this->assertNull($result);
    }
}
