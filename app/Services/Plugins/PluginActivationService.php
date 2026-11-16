<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PluginActivationService
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginLifecycle $lifecycle,
    ) {}

    public function activate(string $pluginKey): PluginInstallation
    {
        return DB::transaction(function () use ($pluginKey): PluginInstallation {
            $plugin = PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
                throw new InvalidArgumentException('Only an installed or inactive plugin can be activated.');
            }

            $this->assertTrustedAndCompatible($plugin);

            $dependencyKeys = $plugin->manifest['dependencies'] ?? [];
            if ($dependencyKeys !== []) {
                $dependencies = PluginInstallation::query()
                    ->whereIn('plugin_key', $dependencyKeys)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('plugin_key');

                foreach ($dependencyKeys as $dependencyKey) {
                    if (! isset($dependencies[$dependencyKey]) || $dependencies[$dependencyKey]->status !== 'active') {
                        throw new InvalidArgumentException('Activate required plugin first: '.$dependencyKey);
                    }
                }
            }

            $conflicts = $plugin->manifest['conflicts'] ?? [];
            if ($conflicts !== [] && PluginInstallation::query()
                ->whereIn('plugin_key', $conflicts)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists()) {
                throw new InvalidArgumentException('A conflicting plugin is already active.');
            }

            return $this->lifecycle->transition($pluginKey, 'active');
        });
    }

    public function deactivate(string $pluginKey): PluginInstallation
    {
        return DB::transaction(function () use ($pluginKey): PluginInstallation {
            $plugin = PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($plugin->status !== 'active') {
                throw new InvalidArgumentException('Only an active plugin can be deactivated.');
            }

            $activePlugins = PluginInstallation::query()
                ->where('status', 'active')
                ->where('plugin_key', '!=', $pluginKey)
                ->lockForUpdate()
                ->get();

            foreach ($activePlugins as $dependent) {
                if (in_array($pluginKey, $dependent->manifest['dependencies'] ?? [], true)) {
                    throw new InvalidArgumentException(
                        'Deactivate dependent plugin first: '.$dependent->plugin_key
                    );
                }
            }

            return $this->lifecycle->transition($pluginKey, 'inactive');
        });
    }

    private function assertTrustedAndCompatible(PluginInstallation $plugin): void
    {
        $errors = $this->manifestValidator->errors($plugin->manifest ?? []);
        if ($errors !== []) {
            throw new RuntimeException('The plugin manifest is invalid: '.implode(' ', $errors));
        }

        if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
            || (string) $plugin->name !== (string) $plugin->manifest['name']
            || (string) $plugin->version !== (string) $plugin->manifest['version']
            || (string) $plugin->type !== (string) $plugin->manifest['type']) {
            throw new RuntimeException('The plugin manifest does not match its registry identity.');
        }

        if ($plugin->signature_verified_at === null
            || ! is_string($plugin->package_sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $plugin->package_sha256) !== 1
            || ! is_string($plugin->signature_key_id)
            || trim($plugin->signature_key_id) === '') {
            throw new RuntimeException('The plugin does not have verified release metadata.');
        }

        $trustedKeys = config('plugins.trusted_public_keys', []);
        if (! is_array($trustedKeys) || ! array_key_exists($plugin->signature_key_id, $trustedKeys)) {
            throw new RuntimeException('The plugin release signing key is not currently trusted.');
        }

        $this->integrityVerifier->verify($plugin);

        $requirements = $plugin->manifest['requires'];
        $versions = [
            'php' => PHP_VERSION,
            'laravel' => \Illuminate\Foundation\Application::VERSION,
            'lms' => (string) (config('plugins.lms_version') ?: '1.0.0'),
        ];

        foreach ($versions as $component => $currentVersion) {
            if (! $this->satisfies($currentVersion, (string) $requirements[$component])) {
                throw new InvalidArgumentException(
                    'Plugin is not compatible with this '.$component.' version ('.$currentVersion.').'
                );
            }
        }
    }

    private function satisfies(string $version, string $constraint): bool
    {
        if (class_exists(\Composer\Semver\Semver::class)) {
            return \Composer\Semver\Semver::satisfies($version, $constraint);
        }

        $constraint = trim($constraint);
        if ($constraint === '*') {
            return true;
        }

        if (preg_match('/^(\^|~)(\d+)\.(\d+)(?:\.(\d+))?$/D', $constraint, $matches) === 1) {
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

            if ($major > 0) {
                return version_compare($version, ($major + 1).'.0.0', '<');
            }

            if ($minor > 0) {
                return version_compare($version, '0.'.($minor + 1).'.0', '<');
            }

            return version_compare($version, '0.0.'.($patch + 1), '<');
        }

        if (preg_match('/^(>=|<=|>|<)\s*(\d+\.\d+(?:\.\d+)?)$/D', $constraint, $matches) === 1) {
            return version_compare($version, $matches[2], $matches[1]);
        }

        if (preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $constraint) === 1) {
            return version_compare($version, $constraint, '=');
        }

        throw new InvalidArgumentException('Unsupported plugin compatibility constraint: '.$constraint);
    }
}
