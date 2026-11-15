<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\PluginVersionRecord;
use App\Services\Plugins\PluginVersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class PluginVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(array $overrides = []): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill(array_merge([
            'plugin_key' => 'aec.analytics',
            'name' => 'Analytics',
            'version' => '1.2.0',
            'type' => 'integration',
            'status' => 'installed',
            'manifest' => ['key' => 'aec.analytics'],
            'signature_verified_at' => Carbon::now(),
            'signature_key_id' => 'release-key',
            'package_sha256' => str_repeat('a', 64),
            'package_path' => 'plugins/aec/analytics/1.2.0',
            'installed_at' => Carbon::now(),
            'metadata' => ['integrity_version' => 1, 'installed_file_hashes' => ['src/Plugin.php' => str_repeat('b', 64)]],
        ], $overrides))->save();

        return $plugin;
    }

    public function test_records_verified_version_once_and_keeps_checksum_immutable(): void
    {
        $plugin = $this->plugin();
        $history = app(PluginVersionHistory::class);

        $first = $history->recordInstalledVersion($plugin);
        $again = $history->recordInstalledVersion($plugin);

        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('plugin_version_history', 1);
        $this->assertSame(str_repeat('a', 64), $first->package_sha256);

        $plugin->package_sha256 = str_repeat('c', 64);
        $this->expectException(RuntimeException::class);
        $history->recordInstalledVersion($plugin);
    }

    public function test_unverified_or_uninstalled_packages_cannot_enter_version_history(): void
    {
        $plugin = $this->plugin(['status' => 'uninstalled']);

        $this->expectException(RuntimeException::class);
        app(PluginVersionHistory::class)->recordInstalledVersion($plugin);
    }

    public function test_install_lifecycle_records_version_atomically(): void
    {
        $plugin = $this->plugin([
            'status' => 'staged',
            'installed_at' => null,
        ]);

        $plugin->forceFill([
            'signature_verified_at' => Carbon::now(),
            'package_sha256' => str_repeat('d', 64),
            'signature_key_id' => 'release-key',
            'package_path' => 'plugins/aec/analytics/1.2.0',
        ])->save();

        $installed = app(\App\Services\Plugins\PluginLifecycle::class)
            ->transition('aec.analytics', 'installed');

        $this->assertSame('installed', $installed->status);
        $this->assertDatabaseHas('plugin_version_history', [
            'plugin_key' => 'aec.analytics',
            'version' => '1.2.0',
            'package_sha256' => str_repeat('d', 64),
        ]);
    }
}
