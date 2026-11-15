<?php

namespace App\Services\Modules;

use App\Models\PluginInstallation;
use App\Models\Setting;
use InvalidArgumentException;

class ModuleRegistry
{
    public function catalog(): array
    {
        $definitions = config('modules', []);
        $saved = json_decode((string) Setting::value('module_registry', '{}'), true);
        $saved = is_array($saved) ? $saved : [];
        $catalog = [];

        foreach ($definitions as $key => $definition) {
            $pluginKey = $definition['plugin_key'] ?? null;
            if (is_string($pluginKey) && $pluginKey !== '') {
                $plugin = PluginInstallation::query()->where('plugin_key', $pluginKey)->first();
                $installed = $plugin !== null && in_array($plugin->status, ['installed', 'active', 'inactive', 'failed'], true);
                $enabled = $plugin?->status === 'active';
                $catalog[$key] = [
                    'key' => $key,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'type' => 'plugin',
                    'dependencies' => $definition['dependencies'] ?? [],
                    'protected' => false,
                    'enabled' => $enabled,
                    'installed' => $installed,
                    'status' => $plugin?->status ?? 'not_installed',
                    'plugin_key' => $pluginKey,
                    'managed_by_plugin_manager' => true,
                ];
                continue;
            }

            $enabled = (bool) ($saved[$key] ?? true);
            $catalog[$key] = [
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'dependencies' => $definition['dependencies'] ?? [],
                'protected' => (bool) ($definition['protected'] ?? false),
                'enabled' => $enabled,
                'installed' => true,
                'status' => $enabled ? 'enabled' : 'disabled',
                'managed_by_plugin_manager' => false,
            ];
        }

        return $catalog;
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->catalog()[$key]['enabled'] ?? false);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $catalog = $this->catalog();
        if (! isset($catalog[$key])) {
            throw new InvalidArgumentException('Unknown module.');
        }

        $module = $catalog[$key];
        if ($module['managed_by_plugin_manager'] ?? false) {
            throw new InvalidArgumentException('Manage this optional plugin from Plugin Manager.');
        }
        if (! $enabled && $module['protected']) {
            throw new InvalidArgumentException('This platform module is protected.');
        }

        if ($enabled) {
            foreach ($module['dependencies'] as $dependency) {
                if (! ($catalog[$dependency]['enabled'] ?? false)) {
                    throw new InvalidArgumentException('Enable required module first: '.$dependency);
                }
            }
        } else {
            foreach ($catalog as $dependentKey => $dependent) {
                if ($dependent['enabled'] && in_array($key, $dependent['dependencies'], true)) {
                    throw new InvalidArgumentException('Disable dependent module first: '.$dependentKey);
                }
            }
        }

        $saved = json_decode((string) Setting::value('module_registry', '{}'), true);
        $saved = is_array($saved) ? $saved : [];
        $saved[$key] = $enabled;
        Setting::setValue('module_registry', json_encode($saved, JSON_THROW_ON_ERROR));
    }
}
