<?php

namespace Tests\\Feature;

use App\\Models\\PluginInstallation;
use App\\Models\\PluginVersionRecord;
use App\\Services\\Plugins\\PluginRollbackService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\File;
use RuntimeException;
use Tests\\TestCase;

class PluginRollbackTest extends TestCase
{
    use RefreshDatabase;

    private string $packageDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageDirectory = base_path('plugins/aec/analytics/1.0.0');
        File::deleteDirectory(base_path('plugins/aec/analytics'));
        File::ensureDirectoryExists($this->packageDirectory.'/src');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('plugins/aec/analytics'));
        parent::tearDown();
    }

    private function plugin(string $status = 'inactive'): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.analytics',
            'name' => 'Analytics',
            'version' => '2.0.0',
            'type' => 'integration',
            'status' => $status,
            'manifest' => ['key' => 'aec.analytics'],
            'package_sha256' => str_repeat('c', 64),
            'signature_key_id' => 'release-key-2',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/analytics/2.0.0',
            'metadata' => ['integrity_version' => 1, 'installed_file_hashes' => ['src/Plugin.php' => str_repeat('d', 64)]],
        ])->save();

        return $plugin;
    }

    private function historicalVersion(string $contents = 'trusted old plugin'): PluginVersionRecord
    {
        File::put($this->packageDirectory.'/src/Plugin.php', $contents);
        $hash = hash_file('sha256', $this->packageDirectory.'/src/Plugin.php');

        return PluginVersionRecord::query()->create([
            'plugin_key' => 'aec.analytics',
            'version' => '1.0.0',
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'release-key-1',
            'package_path' => 'plugins/aec/analytics/1.0.0',
            'status' => 'installed',
            'installed_at' => now()->subDay(),
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['src/Plugin.php' => $hash],
                'signature_verified_at' => now()->subDays(2)->toISOString(),
            ],
        ]);
    }

    public function test_rolls_back_to_an_integrity_verified_immutable_version_and_keeps_plugin_inactive(): void
    {
        $plugin = $this->plugin();
        $this->historicalVersion();

        $rolledBack = app(PluginRollbackService::class)->rollback($plugin, '1.0.0');

        $this->assertSame('inactive', $rolledBack->status);
        $this->assertSame('1.0.0', $rolledBack->version);
        $this->assertSame('plugins/aec/analytics/1.0.0', $rolledBack->package_path);
        $this->assertSame(str_repeat('a', 64), $rolledBack->package_sha256);
        $this->assertSame('release-key-1', $rolledBack->signature_key_id);
        $this->assertSame('2.0.0', data_get($rolledBack->metadata, 'rolled_back_from'));
        $this->assertDatabaseCount('plugin_version_history', 1);
    }

    public function test_rejects_rollback_when_historical_package_has_been_tampered_with(): void
    {
        $plugin = $this->plugin();
        $this->historicalVersion();
        File::put($this->packageDirectory.'/src/Plugin.php', 'tampered code');

        try {
            app(PluginRollbackService::class)->rollback($plugin, '1.0.0');
            $this->fail('Tampered historical package should not be restored.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('integrity', strtolower($exception->getMessage()));
        }

        $this->assertSame('inactive', $plugin->fresh()->status);
        $this->assertSame('2.0.0', $plugin->fresh()->version);
    }

    public function test_requires_plugin_to_be_inactive_before_rollback(): void
    {
        $plugin = $this->plugin('active');
        $this->historicalVersion();

        $this->expectException(RuntimeException::class);
        app(PluginRollbackService::class)->rollback($plugin, '1.0.0');
    }

    public function test_rejects_legacy_history_without_full_integrity_evidence(): void
    {
        $plugin = $this->plugin();
        $this->historicalVersion();
        PluginVersionRecord::query()->where('version', '1.0.0')->update([
            'metadata' => json_encode(['integrity_version' => 1, 'file_count' => 1]),
        ]);

        $this->expectException(RuntimeException::class);
        app(PluginRollbackService::class)->rollback($plugin, '1.0.0');
    }
}
