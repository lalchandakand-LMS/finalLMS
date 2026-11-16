<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PluginUninstallRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_filesystem_cleanup_can_be_retried_without_reactivating_the_plugin(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.cleanup-recovery',
            'name' => 'Cleanup Recovery',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'uninstalled',
            'manifest' => [
                'key' => 'test.cleanup-recovery',
                'name' => 'Cleanup Recovery',
                'version' => '1.0.0',
                'type' => 'module',
                'dependencies' => [],
            ],
            'package_path' => 'plugins/test/cleanup-recovery/1.0.0',
        ])->save();

        $lifecycle = app(PluginLifecycle::class);
        $failed = $lifecycle->transition('test.cleanup-recovery', 'failed', [
            'health_status' => 'failed',
            'health_message' => 'Package cleanup failed; retry uninstall.',
        ]);

        $this->assertSame('failed', $failed->status);
        $this->assertSame('failed', $failed->health_status);
        $this->assertSame('Package cleanup failed; retry uninstall.', $failed->health_message);

        try {
            $lifecycle->transition('test.cleanup-recovery', 'active');
            $this->fail('A cleanup-failed plugin must never become active.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $retry = $lifecycle->transition('test.cleanup-recovery', 'uninstalled');
        $this->assertSame('uninstalled', $retry->status);
    }
}
