<?php

namespace App\\Services\\Plugins;

use App\\Models\\PluginInstallation;
use App\\Models\\PluginSetting;
use InvalidArgumentException;
use RuntimeException;

final class PluginSettingsManager
{
    public function set(PluginInstallation $plugin, string $key, string $value): PluginSetting
    {
        $this->assertConfigurable($plugin);
        $this->assertKey($key);

        if ($value === '' || mb_strlen($value) > 4096) {
            throw new InvalidArgumentException('Plugin setting values must contain 1 to 4096 characters.');
        }

        $setting = PluginSetting::query()->firstOrNew([
            'plugin_key' => $plugin->plugin_key,
            'setting_key' => $key,
        ]);
        $setting->encrypted_value = $value;
        $setting->updated_at = now();
        $setting->save();

        return $setting;
    }

    public function get(PluginInstallation $plugin, string $key): ?string
    {
        $this->assertKey($key);

        return PluginSetting::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('setting_key', $key)
            ->value('encrypted_value');
    }

    /** @return list<array{key: string, updated_at: mixed}> */
    public function keys(PluginInstallation $plugin): array
    {
        return PluginSetting::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->orderBy('setting_key')
            ->get(['setting_key', 'updated_at'])
            ->map(fn (PluginSetting $setting): array => [
                'key' => $setting->setting_key,
                'updated_at' => $setting->updated_at,
            ])
            ->all();
    }

    public function forget(PluginInstallation $plugin, string $key): void
    {
        $this->assertKey($key);

        PluginSetting::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('setting_key', $key)
            ->delete();
    }

    private function assertConfigurable(PluginInstallation $plugin): void
    {
        if (in_array($plugin->status, ['uninstalled', 'failed'], true)) {
            throw new RuntimeException('Settings cannot be changed for an uninstalled or failed plugin.');
        }
    }

    private function assertKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,119}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Invalid plugin setting key.');
        }
    }
}
