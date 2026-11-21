<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PluginSettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(string $status = 'inactive', string $key = 'aec.test-settings'): PluginInstallation
    {
        $id = DB::table('plugin_registry')->insertGetId([
            'plugin_key' => $key,
            'name' => 'Test Settings',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => json_encode(['key' => $key, 'permissions' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PluginInstallation::query()->findOrFail($id);
    }

    private function schemaPlugin(): PluginInstallation
    {
        $plugin = $this->plugin('inactive', 'aec.test-settings-schema');
        $plugin->forceFill(['manifest' => [
            'key' => $plugin->plugin_key,
            'settings_schema' => [
                'endpoint' => ['label' => 'API endpoint', 'type' => 'url', 'required' => true],
                'contact_email' => ['label' => 'Contact email', 'type' => 'email'],
                'retry_count' => ['label' => 'Retry count', 'type' => 'integer'],
                'enabled' => ['label' => 'Enabled', 'type' => 'boolean'],
                'region' => ['label' => 'Region', 'type' => 'select', 'options' => ['asia', 'europe']],
                'api_secret' => ['label' => 'API secret', 'type' => 'secret'],
            ],
        ]])->save();

        return $plugin->fresh();
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

    public function test_manifest_settings_schema_enforces_typed_values_and_encryption(): void
    {
        $plugin = $this->schemaPlugin();
        $manager = app(PluginSettingsManager::class);

        $manager->set($plugin, 'endpoint', 'https://api.example.com/v1');
        $manager->set($plugin, 'contact_email', 'ops@example.com');
        $manager->set($plugin, 'retry_count', '3');
        $manager->set($plugin, 'enabled', '1');
        $manager->set($plugin, 'region', 'asia');
        $manager->set($plugin, 'api_secret', 'schema-secret-value');

        $this->assertSame('https://api.example.com/v1', $manager->get($plugin, 'endpoint'));
        $this->assertSame('3', $manager->get($plugin, 'retry_count'));
        $this->assertSame('1', $manager->get($plugin, 'enabled'));
        $stored = DB::table('plugin_settings')->where('plugin_key', $plugin->plugin_key)
            ->where('setting_key', 'api_secret')->value('encrypted_value');
        $this->assertStringNotContainsString('schema-secret-value', $stored);
    }

    public function test_manifest_settings_schema_rejects_undeclared_keys_and_invalid_typed_values(): void
    {
        $plugin = $this->schemaPlugin();
        $manager = app(PluginSettingsManager::class);

        foreach ([
            ['unknown_setting', 'value'],
            ['endpoint', 'http://api.example.com'],
            ['contact_email', 'not-an-email'],
            ['retry_count', '3.5'],
            ['enabled', 'yes'],
            ['region', 'antarctica'],
        ] as [$key, $value]) {
            try {
                $manager->set($plugin, $key, $value);
                $this->fail("Expected {$key} to reject its invalid value.");
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('plugin_settings', [
                    'plugin_key' => $plugin->plugin_key,
                    'setting_key' => $key,
                ]);
            }
        }
    }

    public function test_admin_sees_manifest_defined_settings_without_existing_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->schemaPlugin();

        $this->actingAs($admin)->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('API endpoint')
            ->assertSee('Contact email')
            ->assertSee('API secret')
            ->assertSee('name="setting_key" value="endpoint"', false)
            ->assertDontSee('schema-secret-value');
    }

    public function test_admin_cannot_save_invalid_manifest_defined_setting_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $plugin = $this->schemaPlugin();

        $this->actingAs($admin)
            ->from(route('admin.plugins.index'))
            ->post(route('admin.plugins.settings.store', $plugin->plugin_key), [
                'setting_key' => 'endpoint',
                'setting_value' => 'http://insecure.example.com',
            ])
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHasErrors('setting_value');

        $this->assertDatabaseMissing('plugin_settings', [
            'plugin_key' => $plugin->plugin_key,
            'setting_key' => 'endpoint',
        ]);
        $this->assertArrayNotHasKey('setting_value', session()->get('_old_input', []));
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
                $manager->set($this->plugin($status, 'aec.test-settings-'.$status), 'api.secret', 'value');
                $this->fail('Expected a failed or uninstalled plugin to reject configuration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('cannot be changed', $exception->getMessage());
            }
        }
    }

    public function test_secret_setting_values_are_not_flashed_after_validation_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $plugin = $this->plugin();

        $this->actingAs($admin)
            ->from(route('admin.plugins.index'))
            ->post(route('admin.plugins.settings.store', $plugin->plugin_key), [
                'setting_key' => 'Invalid key',
                'setting_value' => 'do-not-flash-this-secret',
            ])
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHasErrors('setting_key');

        $this->assertArrayNotHasKey('setting_value', session()->get('_old_input', []));
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
