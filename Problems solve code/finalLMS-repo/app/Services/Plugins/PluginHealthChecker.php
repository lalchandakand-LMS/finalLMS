<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PluginHealthChecker
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginCompatibilityChecker $compatibilityChecker,
        private readonly PluginIntegrityVerifier $integrityVerifier,
    ) {
    }

    /**
     * Evaluate signature trust, compatibility, dependencies, integrity, and migration status,
     * persisting health_status, health_message, and last_health_check_at.
     *
     * @return array{status: string, message: string, issues: list<string>, warnings: list<string>}
     */
    public function checkAndRecord(PluginInstallation $plugin): array
    {
        $issues = [];
        $warnings = [];

        if ($plugin->status === 'uninstalled') {
            $status = 'unknown';
            $message = 'Plugin package is uninstalled; historical data and migration logs are preserved.';
            $plugin->forceFill([
                'health_status' => $status,
                'health_message' => $message,
                'last_health_check_at' => now(),
            ])->save();

            return ['status' => $status, 'message' => $message, 'issues' => [], 'warnings' => []];
        }

        if ($plugin->status === 'discovered') {
            $warnings[] = 'Package discovered on disk; upload a signed ZIP archive before activation.';
        } else {
            $trustedKeys = config('plugins.trusted_public_keys', []);
            if ($plugin->signature_verified_at === null
                || ! is_string($plugin->signature_key_id)
                || ! is_array($trustedKeys)
                || ! array_key_exists($plugin->signature_key_id, $trustedKeys)) {
                $issues[] = 'Release signing key is missing or no longer trusted.';
            }
        }

        $manifestErrors = $this->manifestValidator->errors($plugin->manifest ?? []);
        if ($manifestErrors !== []) {
            $issues[] = 'Invalid manifest: '.implode(' ', $manifestErrors);
        } else {
            $compat = $this->compatibilityChecker->check($plugin->manifest ?? []);
            if (! $compat['compatible']) {
                $issues[] = 'Compatibility failed: '.implode(' ', $compat['errors']);
            }
        }

        foreach (($plugin->manifest['dependencies'] ?? []) as $dependency) {
            if (! PluginInstallation::query()->where('plugin_key', $dependency)->where('status', 'active')->exists()) {
                $warnings[] = 'Required dependency is not active: '.$dependency;
            }
        }

        foreach (($plugin->manifest['conflicts'] ?? []) as $conflict) {
            if (PluginInstallation::query()->where('plugin_key', $conflict)->where('status', 'active')->exists()) {
                $issues[] = 'Conflicting plugin is active: '.$conflict;
            }
        }

        if (in_array($plugin->status, ['installed', 'active', 'inactive'], true)) {
            try {
                $this->integrityVerifier->verify($plugin);
            } catch (Throwable $exception) {
                $issues[] = $exception->getMessage();
            }
        }

        $failedMigrations = DB::table('plugin_migrations')
            ->where('plugin_key', $plugin->plugin_key)
            ->where('status', 'failed')
            ->count();
        if ($failedMigrations > 0) {
            $warnings[] = sprintf('%d plugin migration(s) previously failed.', $failedMigrations);
        }

        if ($issues !== []) {
            $status = 'failed';
            $message = implode(' ', $issues);
        } elseif ($warnings !== []) {
            $status = 'degraded';
            $message = implode(' ', $warnings);
        } else {
            $status = 'healthy';
            $message = 'All signature, compatibility, dependency, integrity, and migration checks passed.';
        }

        $plugin->forceFill([
            'health_status' => $status,
            'health_message' => mb_substr($message, 0, 1000),
            'last_health_check_at' => now(),
        ])->save();

        return [
            'status' => $status,
            'message' => $message,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }
}
