<?php

namespace App\\Services\\Plugins;

use App\\Models\\PluginInstallation;
use Illuminate\\Foundation\\Application;
use InvalidArgumentException;

final class PluginCompatibilityChecker
{
    /**
     * Return compatibility and dependency errors before a package is staged.
     *
     * @return list<string>
     */
    public function errors(array $manifest): array
    {
        $errors = [];
        $requirements = $manifest['requires'] ?? [];

        foreach ([
            'php' => PHP_VERSION,
            'laravel' => Application::VERSION,
            'lms' => (string) config('plugins.platform_version', '1.0.0'),
        ] as $component => $currentVersion) {
            $constraint = $requirements[$component] ?? null;
            if (! is_string($constraint) || ! $this->matches($currentVersion, $constraint)) {
                $errors[] = "Plugin requires an incompatible {$component} version.";
            }
        }

        foreach (($manifest['dependencies'] ?? []) as $dependency) {
            $installed = PluginInstallation::query()
                ->where('plugin_key', $dependency)
                ->whereIn('status', ['installed', 'active', 'inactive'])
                ->exists();

            if (! $installed) {
                $errors[] = "Required plugin is not installed: {$dependency}.";
            }
        }

        foreach (($manifest['conflicts'] ?? []) as $conflict) {
            $present = PluginInstallation::query()
                ->where('plugin_key', $conflict)
                ->whereNotIn('status', ['failed', 'uninstalled'])
                ->exists();

            if ($present) {
                $errors[] = "Plugin conflicts with an installed plugin: {$conflict}.";
            }
        }

        return $errors;
    }

    private function matches(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '*') {
            return true;
        }

        if (preg_match('/^(>=|>|<=|<|=)?\\s*(\\d+\\.\\d+\\.\\d+)$/D', $constraint, $matches) === 1) {
            $operator = $matches[1] !== '' ? $matches[1] : '=';
            return version_compare($version, $matches[2], $operator);
        }

        if (preg_match('/^\\^([0-9]+)\\.([0-9]+)(?:\\.([0-9]+))?$/D', $constraint, $matches) === 1) {
            $minimum = $matches[1].'.'.$matches[2].'.'.($matches[3] ?? '0');
            $major = (int) $matches[1];
            $upper = $major > 0
                ? ($major + 1).'.0.0'
                : '0.'.((int) $matches[2] + 1).'.0';

            return version_compare($version, $minimum, '>=')
                && version_compare($version, $upper, '<');
        }

        if (preg_match('/^~([0-9]+)\\.([0-9]+)(?:\\.([0-9]+))?$/D', $constraint, $matches) === 1) {
            $minimum = $matches[1].'.'.$matches[2].'.'.($matches[3] ?? '0');
            $upper = ((int) $matches[1]).'.'.((int) $matches[2] + 1).'.0';

            return version_compare($version, $minimum, '>=')
                && version_compare($version, $upper, '<');
        }

        throw new InvalidArgumentException('Unsupported plugin version constraint: '.$constraint);
    }
}
