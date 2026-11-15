<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginDeactivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PluginDeactivatorTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(string $key, string $status, array $dependencies = []): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $key,
            'name' => $key,
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => ['key' => $key, 'dependencies' => $dependencies],
        ])->save();

        return $plugin;
    }

    public function test_active_plugin_can_be_deactivated_without_removing_registry_data(): void
    {
        $plugin = $this->plugin('aec.analytics', 'active');

        $inactive = app(PluginDeactivator::class)->deactivate($plugin);

        $this->assertSame('inactive', $inactive->status);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.analytics',
            'status' => 'inactive',
        ]);
    }

    public function test_deactivation_is_blocked_when_an_active_plugin_depends_on_it(): void
    {
        $plugin = $this->plugin('aec.storage', 'active');
        $this->plugin('aec.drive-backup', 'active', ['aec.storage']);

        $this->expectException(RuntimeException::class);
        app(PluginDeactivator::class)->deactivate($plugin);
    }

    public function test_inactive_plugin_cannot_be_deactivated_twice(): void
    {
        $plugin = $this->plugin('aec.analytics', 'inactive');

        $this->expectException(RuntimeException::class);
        app(PluginDeactivator::class)->deactivate($plugin);
    }
}
