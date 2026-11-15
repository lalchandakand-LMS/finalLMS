<?php

namespace App\Services\Modules;

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
