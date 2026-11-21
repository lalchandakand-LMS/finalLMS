<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use App\Models\PluginSetting;
use InvalidArgumentException;
use RuntimeException;

final class PluginSettingsManager
{
    public function set(PluginInstallation $plugin, string $key, string $value): PluginSetting
    {
        $this->assertConfigurable($plugin);
        $this->assertKey($key);
        $value = $this->validatedValue($plugin, $key, $value);

        $setting = PluginSetting::query()->firstOrNew([
            'plugin_key' => $plugin->plugin_key,
            'setting_key' => $key,
        ]);
        $setting->encrypted_value = $value;
        $setting->updated_at = now();
        $setting->save();

        return $setting;
    }

    public function validatedValue(PluginInstallation $plugin, string $key, string $value): string
    {
        $this->assertKey($key);
        if ($value === '' || mb_strlen($value) > 4096) {
            throw new InvalidArgumentException('Plugin setting values must contain 1 to 4096 characters.');
        }

        $schema = $plugin->manifest['settings_schema'] ?? null;
        if (! is_array($schema) || array_is_list($schema)) {
            return $value; // Backward-compatible legacy key/value settings.
        }
        $definition = $schema[$key] ?? null;
        if (! is_array($definition)) {
            throw new InvalidArgumentException('This setting is not declared in the signed plugin manifest.');
        }

        $type = $definition['type'] ?? 'text';
        return match ($type) {
            'text', 'secret' => $value,
            'url' => $this->validateHttpsUrl($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? $value
                : throw new InvalidArgumentException('Enter a valid email address.'),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? $value
                : throw new InvalidArgumentException('Enter a valid whole number.'),
            'boolean' => in_array($value, ['0', '1'], true)
                ? $value
                : throw new InvalidArgumentException('Choose a valid true/false value.'),
            'select' => in_array($value, $definition['options'] ?? [], true)
                ? $value
                : throw new InvalidArgumentException('Choose one of the options declared by the plugin.'),
            default => throw new InvalidArgumentException('The signed plugin setting type is unsupported.'),
        };
    }

    public function definition(PluginInstallation $plugin, string $key): ?array
    {
        $schema = $plugin->manifest['settings_schema'] ?? null;
        return is_array($schema) && ! array_is_list($schema) && is_array($schema[$key] ?? null)
            ? $schema[$key]
            : null;
    }

    public function isRequired(PluginInstallation $plugin, string $key): bool
    {
        return (bool) ($this->definition($plugin, $key)['required'] ?? false);
    }

    public function get(PluginInstallation $plugin, string $key): ?string
    {
        $this->assertKey($key);

        $setting = PluginSetting::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('setting_key', $key)
            ->first();

        return $setting?->encrypted_value;
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

    private function validateHttpsUrl(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('Enter a valid HTTPS URL.');
        }

        return $value;
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
