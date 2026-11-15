<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\PluginSetting;
use App\Services\Plugins\PluginSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PluginSettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(string $status = 'installed'): PluginInstallation
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.sms-provider',
            'name' => 'SMS Provider',
            'version' => '1.0.0',
            'type' => 'integration',
            'status' => $status,
            'manifest' => ['key' => 'aec.sms-provider'],
        ])->save();

        return $plugin;
    }

    public function test_setting_values_are_encrypted_at_rest_and_never_in_key_listing(): void
    {
        $plugin = $this->plugin();
        $manager = app(PluginSettingsManager::class);
        $secret = 'provider-secret-token-123';

        $manager->set($plugin, 'sms.api_token', $secret);

        $stored = DB::table('plugin_settings')
            ->where('plugin_key', $plugin->plugin_key)
            ->where('setting_key', 'sms.api_token')
            ->value('encrypted_value');

        $this->assertNotSame($secret, $stored);
        $this->assertSame($secret, $manager->get($plugin, 'sms.api_token'));
        $this->assertSame([[
            'key' => 'sms.api_token',
            'updated_at' => $manager->keys($plugin)[0]['updated_at'],
        ]], $manager->keys($plugin));
        $this->assertArrayNotHasKey('encrypted_value', (new PluginSetting())->toArray());
    }

    public function test_invalid_setting_keys_and_empty_or_oversized_values_are_rejected(): void
    {
        $plugin = $this->plugin();
        $manager = app(PluginSettingsManager::class);

        try {
            $manager->set($plugin, '../secret', 'value');
            $this->fail('Unsafe setting keys must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('plugin_settings', 0);
        }

        $this->expectException(InvalidArgumentException::class);
        $manager->set($plugin, 'sms.token', '');
    }

    public function test_settings_cannot_be_changed_after_uninstall_or_failed_install(): void
    {
        $manager = app(PluginSettingsManager::class);

        $this->expectException(RuntimeException::class);
        $manager->set($this->plugin('uninstalled'), 'sms.token', 'value');
    }

    public function test_setting_can_be_replaced_and_deleted_without_exposing_its_value(): void
    {
        $plugin = $this->plugin();
        $manager = app(PluginSettingsManager::class);

        $manager->set($plugin, 'sms.api_token', 'old-secret');
        $manager->set($plugin, 'sms.api_token', 'new-secret');

        $this->assertSame('new-secret', $manager->get($plugin, 'sms.api_token'));
        $manager->forget($plugin, 'sms.api_token');
        $this->assertNull($manager->get($plugin, 'sms.api_token'));
    }
}
