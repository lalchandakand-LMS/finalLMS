<?php

namespace App\Services\Plugins;

final class PluginManifestValidator
{
    private const ALLOWED_FIELDS = [
        'schema_version', 'key', 'name', 'version', 'type', 'entrypoint',
        'requires', 'dependencies', 'conflicts', 'portals', 'permissions',
        'capabilities', 'migrations', 'settings_schema',
    ];

    private const ALLOWED_TYPES = ['module', 'integration', 'builder', 'portal', 'ai', 'provider'];
    private const ALLOWED_PORTALS = ['admin', 'branch', 'teacher', 'student', 'guardian', 'support_staff', 'public'];

    public function errors(array $manifest): array
    {
        $errors = [];
        foreach (array_diff(array_keys($manifest), self::ALLOWED_FIELDS) as $field) {
            $errors[] = "Unsupported manifest field: {$field}.";
        }
        if (($manifest['schema_version'] ?? null) !== 1) $errors[] = 'Unsupported or missing schema_version.';
        if (! $this->isPluginKey($manifest['key'] ?? null)) $errors[] = 'Invalid or missing plugin key.';
        if (! $this->isNonEmptyString($manifest['name'] ?? null, 120)) $errors[] = 'Invalid or missing plugin name.';
        if (! $this->isSemanticVersion($manifest['version'] ?? null)) $errors[] = 'Invalid or missing semantic version.';
        if (! in_array($manifest['type'] ?? null, self::ALLOWED_TYPES, true)) $errors[] = 'Invalid or missing plugin type.';
        if (! $this->isSafeRelativePath($manifest['entrypoint'] ?? null, ['php'])) $errors[] = 'Entrypoint must be a safe relative PHP path.';

        $requires = $manifest['requires'] ?? null;
        if (! is_array($requires)) {
            $errors[] = 'requires must be an object.';
        } else {
            foreach (['php', 'laravel', 'lms'] as $requirement) {
                if (! $this->isNonEmptyString($requires[$requirement] ?? null, 80)) $errors[] = "Missing or invalid requires.{$requirement}.";
            }
            foreach (array_diff(array_keys($requires), ['php', 'laravel', 'lms']) as $field) $errors[] = "Unsupported compatibility requirement: {$field}.";
        }

        foreach (['dependencies', 'conflicts', 'portals', 'permissions', 'capabilities'] as $field) {
            if (! is_array($manifest[$field] ?? null) || ! array_is_list($manifest[$field])) {
                $errors[] = "{$field} must be a list.";
                continue;
            }
            if (count($manifest[$field]) !== count(array_unique($manifest[$field], SORT_REGULAR))) $errors[] = "{$field} must not contain duplicates.";
            foreach ($manifest[$field] as $value) {
                if (! is_string($value) || trim($value) === '') {
                    $errors[] = "{$field} entries must be non-empty strings.";
                    break;
                }
            }
        }

        $key = $manifest['key'] ?? null;
        foreach (['dependencies', 'conflicts'] as $field) {
            foreach (($manifest[$field] ?? []) as $reference) {
                if (! $this->isPluginKey($reference)) {
                    $errors[] = "Invalid plugin reference in {$field}.";
                    break;
                }
                if (is_string($key) && hash_equals($key, $reference)) {
                    $errors[] = "A plugin cannot list itself in {$field}.";
                    break;
                }
            }
        }
        foreach (($manifest['portals'] ?? []) as $portal) {
            if (! in_array($portal, self::ALLOWED_PORTALS, true)) {
                $errors[] = 'Manifest contains an unsupported portal.';
                break;
            }
        }
        if (array_key_exists('migrations', $manifest) && ! $this->isSafeRelativePath($manifest['migrations'], [])) {
            $errors[] = 'migrations must be a safe relative directory path.';
        }

        if (array_key_exists('settings_schema', $manifest)) {
            $schema = $manifest['settings_schema'];
            if (! is_array($schema) || array_is_list($schema) || count($schema) > 30) {
                $errors[] = 'settings_schema must be an object with no more than 30 settings.';
            } else {
                foreach ($schema as $settingKey => $definition) {
                    if (! is_string($settingKey) || preg_match('/^[a-z][a-z0-9_.-]{0,119}$/D', $settingKey) !== 1) {
                        $errors[] = 'settings_schema contains an invalid setting key.';
                        continue;
                    }
                    if (! is_array($definition) || array_is_list($definition)) {
                        $errors[] = "settings_schema.{$settingKey} must be an object.";
                        continue;
                    }
                    $allowed = ['label', 'type', 'required', 'options', 'default'];
                    foreach (array_diff(array_keys($definition), $allowed) as $field) {
                        $errors[] = "Unsupported settings_schema.{$settingKey}.{$field}.";
                    }
                    if (! $this->isNonEmptyString($definition['label'] ?? null, 120)) {
                        $errors[] = "settings_schema.{$settingKey}.label is required.";
                    }
                    if (! in_array($definition['type'] ?? null, ['text', 'url', 'email', 'integer', 'boolean', 'select', 'secret'], true)) {
                        $errors[] = "settings_schema.{$settingKey}.type is invalid.";
                    }
                    if (array_key_exists('required', $definition) && ! is_bool($definition['required'])) {
                        $errors[] = "settings_schema.{$settingKey}.required must be boolean.";
                    }
                    if (($definition['type'] ?? null) === 'select') {
                        $options = $definition['options'] ?? null;
                        if (! is_array($options) || ! array_is_list($options) || $options === [] || count($options) > 50) {
                            $errors[] = "settings_schema.{$settingKey}.options must be a non-empty list.";
                        } else {
                            foreach ($options as $option) {
                                if (! is_string($option) || trim($option) === '' || mb_strlen($option) > 160) {
                                    $errors[] = "settings_schema.{$settingKey}.options must contain short strings.";
                                    break;
                                }
                            }
                            if (count($options) !== count(array_unique($options))) {
                                $errors[] = "settings_schema.{$settingKey}.options must not contain duplicates.";
                            }
                        }
                    } elseif (array_key_exists('options', $definition)) {
                        $errors[] = "settings_schema.{$settingKey}.options is only valid for select settings.";
                    }
                    if (array_key_exists('default', $definition)) {
                        $default = $definition['default'];
                        if (! is_scalar($default) || is_array($default)) {
                            $errors[] = "settings_schema.{$settingKey}.default must be a scalar.";
                        } elseif (($definition['type'] ?? null) === 'select'
                            && is_array($definition['options'] ?? null)
                            && ! in_array((string) $default, $definition['options'], true)) {
                            $errors[] = "settings_schema.{$settingKey}.default must match one of its options.";
                        }
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    public function isValid(array $manifest): bool
    {
        return $this->errors($manifest) === [];
    }

    private function isPluginKey(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 160 && str_contains($value, '.')
            && preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/D', $value) === 1;
    }

    private function isNonEmptyString(mixed $value, int $maxLength): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maxLength;
    }

    private function isSemanticVersion(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $value) === 1;
    }

    private function isSafeRelativePath(mixed $value, array $extensions): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 240) return false;
        if (str_contains($value, "\\") || str_contains($value, ':') || str_starts_with($value, '/') || str_contains($value, "\0")) return false;
        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9_.-]+$/D', $segment) !== 1) return false;
        }
        return $extensions === [] || in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), $extensions, true);
    }
}
