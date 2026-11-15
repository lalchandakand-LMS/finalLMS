<?php

namespace App\Services\Plugins;

/**
 * Validates declarative plugin metadata only. This class never loads or executes
 * plugin code; package trust and activation are separate lifecycle operations.
 */
final class PluginManifestValidator
{
    private const ALLOWED_FIELDS = [
        'schema_version', 'key', 'name', 'version', 'type', 'entrypoint',
        'requires', 'dependencies', 'conflicts', 'portals', 'permissions',
        'capabilities', 'migrations',
    ];

    private const ALLOWED_TYPES = [
        'module', 'integration', 'builder', 'portal', 'ai', 'provider',
    ];

    private const ALLOWED_PORTALS = [
        'admin', 'branch', 'teacher', 'student', 'guardian', 'public',
    ];

    /**
     * @return list<string> Validation errors. An empty list means structurally valid.
     */
    public function errors(array $manifest): array
    {
        $errors = [];

        foreach (array_diff(array_keys($manifest), self::ALLOWED_FIELDS) as $field) {
            $errors[] = "Unsupported manifest field: {$field}.";
        }

        if (($manifest['schema_version'] ?? null) !== 1) {
            $errors[] = 'Unsupported or missing schema_version.';
        }

        if (! $this->isPluginKey($manifest['key'] ?? null)) {
            $errors[] = 'Invalid or missing plugin key.';
        }

        if (! $this->isNonEmptyString($manifest['name'] ?? null, 120)) {
            $errors[] = 'Invalid or missing plugin name.';
        }

        if (! $this->isSemanticVersion($manifest['version'] ?? null)) {
            $errors[] = 'Invalid or missing semantic version.';
        }

        if (! in_array($manifest['type'] ?? null, self::ALLOWED_TYPES, true)) {
            $errors[] = 'Invalid or missing plugin type.';
        }

        if (! $this->isSafeRelativePath($manifest['entrypoint'] ?? null, ['php'])) {
            $errors[] = 'Entrypoint must be a safe relative PHP path.';
        }

        $requires = $manifest['requires'] ?? null;
        if (! is_array($requires)) {
            $errors[] = 'requires must be an object.';
        } else {
            foreach (['php', 'laravel', 'lms'] as $requirement) {
                if (! $this->isNonEmptyString($requires[$requirement] ?? null, 80)) {
                    $errors[] = "Missing or invalid requires.{$requirement}.";
                }
            }

            foreach (array_diff(array_keys($requires), ['php', 'laravel', 'lms']) as $field) {
                $errors[] = "Unsupported compatibility requirement: {$field}.";
            }
        }

        foreach (['dependencies', 'conflicts', 'portals', 'permissions', 'capabilities'] as $field) {
            if (! is_array($manifest[$field] ?? null) || ! array_is_list($manifest[$field])) {
                $errors[] = "{$field} must be a list.";
                continue;
            }

            if (count($manifest[$field]) !== count(array_unique($manifest[$field], SORT_REGULAR))) {
                $errors[] = "{$field} must not contain duplicates.";
            }

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

        foreach (($manifest['permissions'] ?? []) as $permission) {
            if (! is_string($permission) || preg_match('/^[a-z][a-z0-9_.:-]{0,119}$/D', $permission) !== 1) {
                $errors[] = 'Manifest permissions must be explicit names; wildcard and self-granted permissions are forbidden.';
                break;
            }
        }

        foreach (($manifest['capabilities'] ?? []) as $capability) {
            if (! is_string($capability) || preg_match('/^[a-z][a-z0-9_.-]{0,119}$/D', $capability) !== 1) {
                $errors[] = 'Manifest capabilities must use explicit lowercase identifiers.';
                break;
            }
        }

        if (array_key_exists('migrations', $manifest)
            && ! $this->isSafeRelativePath($manifest['migrations'], [])) {
            $errors[] = 'migrations must be a safe relative directory path.';
        }

        return array_values(array_unique($errors));
    }

    public function isValid(array $manifest): bool
    {
        return $this->errors($manifest) === [];
    }

    private function isPluginKey(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 160
            && str_contains($value, '.')
            && preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/D', $value) === 1;
    }

    private function isNonEmptyString(mixed $value, int $maxLength): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && mb_strlen($value) <= $maxLength;
    }

    private function isSemanticVersion(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $value) === 1;
    }

    /**
     * Reject absolute paths, traversal, stream wrappers, backslashes and
     * platform-specific separators before a manifest path is ever resolved.
     *
     * @param list<string> $extensions
     */
    private function isSafeRelativePath(mixed $value, array $extensions): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 240) {
            return false;
        }

        if (str_contains($value, "\\") || str_contains($value, ':')
            || str_starts_with($value, '/') || str_contains($value, "\0")) {
            return false;
        }

        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9_.-]+$/D', $segment) !== 1) {
                return false;
            }
        }

        if ($extensions !== []) {
            return in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), $extensions, true);
        }

        return true;
    }
}
