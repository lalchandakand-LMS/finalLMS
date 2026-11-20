<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePluginJobActive;
use App\Jobs\Plugins\PluginJob;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
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
                'dependencies' => [],
                'conflicts' => [],
                'portals' => ['admin'],
                'permissions' => ['notifications.send'],
                'capabilities' => [],
            ],
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'test-release-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/test-notifier/1.0.0',
            'metadata' => ['granted_permissions' => $grants],
        ])->save();

        return $plugin;
    }

    private function runGuard(
        Closure $next,
        ?bool $signatureValid = true,
        ?RuntimeException $integrityFailure = null,
    ): mixed {
        $signatureVerifier = Mockery::mock(PluginSignatureVerifier::class);
        $integrityVerifier = Mockery::mock(PluginIntegrityVerifier::class);

        if ($signatureValid === null) {
            $signatureVerifier->shouldNotReceive('verifyInstalledSignature');
            $integrityVerifier->shouldNotReceive('verify');
        } elseif ($signatureValid === false) {
            $signatureVerifier->shouldReceive('verifyInstalledSignature')->once()->andReturn(false);
            $integrityVerifier->shouldNotReceive('verify');
        } else {
            $signatureVerifier->shouldReceive('verifyInstalledSignature')->once()->andReturn(true);
            if ($integrityFailure) {
                $integrityVerifier->shouldReceive('verify')->once()->andThrow($integrityFailure);
            } else {
                $integrityVerifier->shouldReceive('verify')->once();
            }
        }

        return (new EnsurePluginJobActive('aec.test-notifier', 'notifications.send'))
            ->handle(new \stdClass(), $next, $signatureVerifier, $integrityVerifier);
    }

    public function test_plugin_job_base_class_attaches_the_lifecycle_guard(): void
    {
        $job = new class('aec.test-notifier', 'notifications.send') extends PluginJob {
            public function handle(): void {}
        };

        $this->assertCount(1, $job->middleware());
        $this->assertInstanceOf(EnsurePluginJobActive::class, $job->middleware()[0]);
    }

    public function test_registry_infrastructure_failure_throws_for_queue_retry(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('plugin_registry')
            ->andThrow(new RuntimeException('database unavailable'));

        $this->expectException(RuntimeException::class);
        $this->runGuard(fn () => 'must-not-run', null);
    }

    public function test_active_signed_intact_plugin_with_admin_grant_can_run_queued_work(): void
    {
        $this->signedPlugin();
        $called = false;
        $result = $this->runGuard(function () use (&$called): string {
            $called = true;

            return 'executed';
        });

        $this->assertTrue($called);
        $this->assertSame('executed', $result);
    }

    public function test_deactivated_plugin_queued_work_is_dropped_before_signature_checks(): void
    {
        $this->signedPlugin('inactive');
        $called = false;
        $result = $this->runGuard(function () use (&$called): string {
            $called = true;

            return 'executed';
        }, null);

        $this->assertFalse($called);
        $this->assertNull($result);
    }

    public function test_revoked_permission_drops_already_queued_work(): void
    {
        $this->signedPlugin('active', []);
        $called = false;
        $result = $this->runGuard(function () use (&$called): string {
            $called = true;

            return 'executed';
        }, null);

        $this->assertFalse($called);
        $this->assertNull($result);
    }

    public function test_legacy_record_without_persisted_signature_is_not_allowed_to_run(): void
    {
        $this->signedPlugin();
        $called = false;
        $result = $this->runGuard(function () use (&$called): string {
            $called = true;

            return 'executed';
        }, false);

        $this->assertFalse($called);
        $this->assertNull($result);
    }

    public function test_tampered_installed_plugin_files_drop_queued_work(): void
    {
        $this->signedPlugin();
        $called = false;
        $result = $this->runGuard(
            function () use (&$called): string {
                $called = true;

                return 'executed';
            },
            true,
            new RuntimeException('Installed plugin integrity check failed.'),
        );

        $this->assertFalse($called);
        $this->assertNull($result);
    }
}
