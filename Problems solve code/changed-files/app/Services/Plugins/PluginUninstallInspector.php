<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;

final class PluginUninstallInspector
{
    /**
     * Build preflight retention & dependency inspection data for safe uninstall decisions.
     *
     * @return array{
     *     can_uninstall: bool,
     *     active_dependants: list<string>,
     *     completed_migrations: int,
     *     total_migrations: int,
     *     version_history_count: int,
     *     retention_summary: string
     * }
     */
    public function preflight(PluginInstallation $plugin): array
    {
        $dependants = PluginInstallation::query()
            ->where('status', 'active')
            ->where('plugin_key', '!=', $plugin->plugin_key)
            ->get()
            ->filter(fn (PluginInstallation $candidate): bool => in_array($plugin->plugin_key, $candidate->manifest['dependencies'] ?? [], true))
            ->pluck('plugin_key')
            ->values()
            ->all();

        $completedMigrations = DB::table('plugin_migrations')
            ->where('plugin_key', $plugin->plugin_key)
            ->where('status', 'completed')
            ->count();

        $totalMigrations = DB::table('plugin_migrations')
            ->where('plugin_key', $plugin->plugin_key)
            ->count();

        $history = is_array($plugin->metadata['version_history'] ?? null)
            ? $plugin->metadata['version_history']
            : [];

        $canUninstall = in_array($plugin->status, ['installed', 'inactive', 'failed'], true) && $dependants === [];

        $retentionSummary = $dependants !== []
            ? 'Blocked by active dependants: '.implode(', ', $dependants)
            : ($completedMigrations > 0
                ? sprintf('%d completed migration(s) & LMS tables retained on uninstall.', $completedMigrations)
                : 'Application data and registry history retained on uninstall.');

        return [
            'can_uninstall' => $canUninstall,
            'active_dependants' => $dependants,
            'completed_migrations' => $completedMigrations,
            'total_migrations' => $totalMigrations,
            'version_history_count' => count($history),
            'retention_summary' => $retentionSummary,
        ];
    }

    /**
     * Produce a sanitized, secret-free JSON metadata and migration export for a plugin.
     *
     * @return array<string, mixed>
     */
    public function exportPayload(PluginInstallation $plugin): array
    {
        $manifest = is_array($plugin->manifest) ? $plugin->manifest : [];
        $safeManifest = array_intersect_key($manifest, array_flip([
            'schema_version',
            'key',
            'name',
            'version',
            'type',
            'entrypoint',
            'requires',
            'dependencies',
            'conflicts',
            'portals',
            'permissions',
            'capabilities',
            'migrations',
        ]));

        $rawHistory = is_array($plugin->metadata['version_history'] ?? null)
            ? $plugin->metadata['version_history']
            : [];
        $safeHistory = array_values(array_map(static function (mixed $entry): array {
            if (! is_array($entry)) {
                return [];
            }

            return [
                'version' => $entry['version'] ?? null,
                'package_sha256' => $entry['package_sha256'] ?? null,
                'signature_key_id' => $entry['signature_key_id'] ?? null,
                'replaced_at' => $entry['replaced_at'] ?? null,
                'replaced_by_version' => $entry['replaced_by_version'] ?? null,
            ];
        }, $rawHistory));

        $migrations = DB::table('plugin_migrations')
            ->where('plugin_key', $plugin->plugin_key)
            ->orderBy('id')
            ->get(['plugin_version', 'migration', 'status', 'started_at', 'completed_at'])
            ->map(static fn ($row): array => [
                'plugin_version' => $row->plugin_version,
                'migration' => $row->migration,
                'status' => $row->status,
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
            ])
            ->values()
            ->all();

        $preflight = $this->preflight($plugin);

        return [
            'exported_at' => now()->toIso8601String(),
            'plugin' => [
                'key' => $plugin->plugin_key,
                'name' => $plugin->name,
                'version' => $plugin->version,
                'type' => $plugin->type,
                'status' => $plugin->status,
                'signature_key_id' => $plugin->signature_key_id,
                'signature_verified_at' => $plugin->signature_verified_at?->toIso8601String(),
                'package_sha256' => $plugin->package_sha256,
                'health_status' => $plugin->health_status,
                'health_message' => $plugin->health_message,
                'last_health_check_at' => $plugin->last_health_check_at?->toIso8601String(),
                'installed_at' => $plugin->installed_at?->toIso8601String(),
                'activated_at' => $plugin->activated_at?->toIso8601String(),
            ],
            'manifest' => $safeManifest,
            'preflight' => $preflight,
            'version_history' => $safeHistory,
            'migrations' => $migrations,
        ];
    }
}
