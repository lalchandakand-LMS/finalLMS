<?php

namespace App\\Services\\Plugins;

use Illuminate\\Foundation\\Application;
use InvalidArgumentException;

final class PluginCompatibilityChecker
{
    public function assertCompatible(array $manifest): void
    {
        $requirements = $manifest['requires'] ?? [];
        if (! is_array($requirements)) {
            throw new InvalidArgumentException('Plugin compatibility requirements are missing.');
        }

        $versions = [
            'php' => PHP_VERSION,
            'laravel' => Application::VERSION,
            'lms' => (string) (config('plugins.lms_version') ?: '1.0.0'),
        ];

        foreach ($versions as $component => $version) {
            $constraint = $requirements[$component] ?? null;
            if (! is_string($constraint) || trim($constraint) === '' || ! $this->satisfies($version, $constraint)) {
                throw new InvalidArgumentException(
                    'Plugin is not compatible with this '.$component.' version ('.$version.').'
                );
            }
        }
    }

    private function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if (class_exists(\\Composer\\Semver\\Semver::class)) {
            try {
                return \\Composer\\Semver\\Semver::satisfies($version, $constraint);
            } catch (\\UnexpectedValueException) {
                return false;
            }
        }

        if ($constraint === '*') {
            return true;
        }

        if (preg_match('/^(\\^|~)(\\d+)\\.(\\d+)(?:\\.(\\d+))?$/D', $constraint, $matches) === 1) {
            $operator = $matches[1];
            $major = (int) $matches[2];
            $minor = (int) $matches[3];
            $patch = (int) ($matches[4] ?? 0);
            $minimum = $major.'.'.$minor.'.'.$patch;
            if (version_compare($version, $minimum, '<')) {
                return false;
            }

            if ($operator === '~') {
                return version_compare($version, $major.'.'.($minor + 1).'.0', '<');
            }

            return $major > 0
                ? version_compare($version, ($major + 1).'.0.0', '<')
                : ($minor > 0
                    ? version_compare($version, '0.'.($minor + 1).'.0', '<')
                    : version_compare($version, '0.0.'.($patch + 1), '<'));
        }

        if (preg_match('/^(>=|<=|>|<)\\s*(\\d+\\.\\d+(?:\\.\\d+)?)$/D', $constraint, $matches) === 1) {
            return version_compare($version, $matches[2], $matches[1]);
        }

        if (preg_match('/^\\d+\\.\\d+(?:\\.\\d+)?$/D', $constraint) === 1) {
            return version_compare($version, $constraint, '=');
        }

        return false;
    }
}
