<?php

namespace App\Services\Plugins;

use RuntimeException;

final class PluginCompatibilityChecker
{
    /**
     * Evaluate manifest runtime requirements against the current PHP, Laravel, and LMS environment.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{
     *     compatible: bool,
     *     errors: list<string>,
     *     versions: array{php: string, laravel: string, lms: string},
     *     checks: array<string, array{required: string, current: string, satisfied: bool}>
     * }
     */
    public function check(array $manifest): array
    {
        $requires = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];
        $currentPhp = PHP_VERSION;
        $currentLaravel = app()->version();
        $currentLms = $this->resolveCurrentLmsVersion();

        $targets = [
            'php' => ['label' => 'PHP', 'current' => $currentPhp],
            'laravel' => ['label' => 'Laravel', 'current' => $currentLaravel],
            'lms' => ['label' => 'LMS', 'current' => $currentLms],
        ];

        $checks = [];
        $errors = [];

        foreach ($targets as $key => $info) {
            $constraint = $requires[$key] ?? null;
            if (! is_string($constraint) || trim($constraint) === '') {
                $checks[$key] = [
                    'required' => 'missing',
                    'current' => $info['current'],
                    'satisfied' => false,
                ];
                $errors[] = sprintf('Missing compatibility constraint for %s.', $info['label']);
                continue;
            }

            $satisfied = $this->satisfies($info['current'], $constraint);
            $checks[$key] = [
                'required' => trim($constraint),
                'current' => $info['current'],
                'satisfied' => $satisfied,
            ];

            if (! $satisfied) {
                $errors[] = sprintf(
                    'Requires %s %s, but running %s.',
                    $info['label'],
                    trim($constraint),
                    $info['current']
                );
            }
        }

        $manifestExtensions = $manifest['requires']['extensions'] ?? [];
        if (is_array($manifestExtensions)) {
            foreach ($manifestExtensions as $extension) {
                if (! is_string($extension) || trim($extension) === '' || ! extension_loaded($extension)) {
                    $errors[] = sprintf('Required PHP extension is not loaded: %s.', is_string($extension) ? $extension : 'invalid');
                }
            }
        }

        return [
            'compatible' => $errors === [],
            'errors' => $errors,
            'versions' => [
                'php' => $currentPhp,
                'laravel' => $currentLaravel,
                'lms' => $currentLms,
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function assertCompatible(array $manifest): void
    {
        $result = $this->check($manifest);
        if (! $result['compatible']) {
            throw new RuntimeException('Plugin compatibility check failed: '.implode(' ', $result['errors']));
        }
    }

    public function resolveCurrentLmsVersion(): string
    {
        $configured = config('plugins.lms_version');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            $raw = trim((string) file_get_contents($versionFile));
            if ($raw !== '' && $raw !== '0.0.0') {
                return $raw;
            }
        }

        return '1.0.0';
    }

    public function satisfies(string $currentVersion, string $constraintExpression): bool
    {
        $current = $this->normalizeVersion($currentVersion);
        if ($current === null) {
            return false;
        }

        $branches = preg_split('/\s*\|\|\s*/', trim($constraintExpression)) ?: [];
        foreach ($branches as $branch) {
            $branch = trim($branch);
            if ($branch === '') {
                continue;
            }

            if ($this->satisfiesAndGroup($current, $branch)) {
                return true;
            }
        }

        return false;
    }

    private function satisfiesAndGroup(string $current, string $group): bool
    {
        $tokens = preg_split('/[\s,]+/', trim($group)) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (! $this->satisfiesSingleConstraint($current, $token)) {
                return false;
            }
        }

        return $tokens !== [];
    }

    private function satisfiesSingleConstraint(string $current, string $constraint): bool
    {
        if ($constraint === '*' || $constraint === 'x') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            $base = $this->parseParts(substr($constraint, 1));
            if ($base === null) {
                return false;
            }

            $lower = sprintf('%d.%d.%d', $base[0], $base[1], $base[2]);
            if ($base[0] > 0) {
                $upper = sprintf('%d.0.0', $base[0] + 1);
            } elseif ($base[1] > 0) {
                $upper = sprintf('0.%d.0', $base[1] + 1);
            } else {
                $upper = sprintf('0.0.%d', $base[2] + 1);
            }

            return version_compare($current, $lower, '>=') && version_compare($current, $upper, '<');
        }

        if (str_starts_with($constraint, '~')) {
            $raw = ltrim(substr($constraint, 1), 'vV');
            $segments = explode('.', $raw);
            $base = $this->parseParts($raw);
            if ($base === null) {
                return false;
            }

            $lower = sprintf('%d.%d.%d', $base[0], $base[1], $base[2]);
            $upper = count($segments) <= 2
                ? sprintf('%d.0.0', $base[0] + 1)
                : sprintf('%d.%d.0', $base[0], $base[1] + 1);

            return version_compare($current, $lower, '>=') && version_compare($current, $upper, '<');
        }

        if (preg_match('/^(\d+)(?:\.(\d+|[*x]))?(?:\.(\d+|[*x]))?$/', ltrim($constraint, 'vV'), $matches) === 1
            && (str_contains($constraint, '*') || str_contains($constraint, 'x'))) {
            $major = (int) $matches[1];
            $minorPart = $matches[2] ?? '*';
            if ($minorPart === '*' || $minorPart === 'x') {
                return version_compare($current, "{$major}.0.0", '>=')
                    && version_compare($current, ($major + 1).'.0.0', '<');
            }
            $minor = (int) $minorPart;

            return version_compare($current, "{$major}.{$minor}.0", '>=')
                && version_compare($current, "{$major}.".($minor + 1).'.0', '<');
        }

        if (preg_match('/^(>=|<=|!=|==|=|>|<)?\s*(.+)$/', $constraint, $matches) === 1) {
            $operator = $matches[1] !== '' ? $matches[1] : '==';
            if ($operator === '=') {
                $operator = '==';
            }
            $target = $this->normalizeVersion($matches[2]);
            if ($target === null) {
                return false;
            }

            return version_compare($current, $target, $operator);
        }

        return false;
    }

    private function normalizeVersion(string $version): ?string
    {
        $parts = $this->parseParts($version);

        return $parts === null ? null : sprintf('%d.%d.%d', $parts[0], $parts[1], $parts[2]);
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private function parseParts(string $version): ?array
    {
        $clean = ltrim(trim($version), 'vV');
        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:[-+].*)?$/', $clean, $matches) !== 1) {
            return null;
        }

        return [
            (int) $matches[1],
            isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0,
            isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0,
        ];
    }
}
