<?php

namespace Tests\\Feature;

use App\\Models\\PluginInstallation;
use App\\Models\\User;
use App\\Services\\Plugins\\PluginSettingsManager;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use Tests\\TestCase;

class PluginSettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(string $status = 'inactive'): PluginInstallation
    {
        $id = DB::table('plugin_registry')->insertGetId([
            'plugin_key' => 'aec.test-settings',
            'name' => 'Test Settings',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => json_encode(['key' => 'aec.test-settings', 'permissions' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PluginInstallation::query()->findOrFail($id);
    }

    public function test_plugin_settings_are_encrypted_at_rest_and_decrypted_for_plugin_services(): void
    {
        $plugin = $this->plugin();
        $manager = app(PluginSettingsManager::class);

        $manager->set($plugin, 'api.secret_key', 'super-secret-value');

        $this->assertSame('super-secret-value', $manager->get($plugin, 'api.secret_key'));
        $stored = DB::table('plugin_settings')->where('plugin_key', $plugin->plugin_key)->value('encrypted_value');
        $this->assertNotSame('super-secret-value', $stored);
        $this->assertStringNotContainsString('super-secret-value', $stored);
        $this->assertSame(['api.secret_key'], array_column($manager->keys($plugin), 'key'));
    }

    public function test_plugin_settings_can_be_updated_and_deleted_without_exposing_values(): void
    {
        $plugin = $this->plugin();
        $manager = app(PluginSettingsManager::class);
        $manager->set($plugin, 'api.token', 'first-value');
        $manager->set($plugin, 'api.token', 'second-value');

        $this->assertSame('second-value', $manager->get($plugin, 'api.token'));
        $manager->forget($plugin, 'api.token');
        $this->assertNull($manager->get($plugin, 'api.token'));
        $this->assertSame([], $manager->keys($plugin));
    }

    public function test_failed_or_uninstalled_plugins_cannot_receive_new_settings(): void
    {
        $manager = app(PluginSettingsManager::class);

        foreach (['failed', 'uninstalled'] as $status) {
            try {
                $manager->set($this->plugin($status), 'api.secret', 'value');
                $this->fail('Expected a failed or uninstalled plugin to reject configuration.');
            } catch (\\RuntimeException $exception) {
                $this->assertStringContainsString('cannot be changed', $exception->getMessage());
            }
        }
    }

    public function test_admin_can_save_encrypted_settings_but_manager_never_renders_the_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $plugin = $this->plugin();
        $this->actingAs($admin)->post(route('admin.plugins.settings.store', $plugin->plugin_key), [
            'setting_key' => 'api.secret',
            'setting_value' => 'never-render-this-secret',
        ])->assertRedirect()->assertSessionHas('success');

        $this->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('api.secret')
            ->assertDontSee('never-render-this-secret');
    }
}
