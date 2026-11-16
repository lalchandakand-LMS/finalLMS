<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use App\Models\PluginVersionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PluginVersionRestorer
{
    public function __construct(
        private readonly PluginSignatureVerifier $signatureVerifier,
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginMigrationRunner $migrationRunner,
        private readonly PluginVersionHistoryRecorder $history,
    ) {
    }

    public function restore(
        PluginInstallation $plugin,
        PluginVersionHistory $snapshotRecord,
        ?User $actor = null,
    ): PluginInstallation {
        if ($snapshotRecord->plugin_key !== $plugin->plugin_key
            || $snapshotRecord->action !== 'version_snapshot') {
            throw new RuntimeException('The selected restore point does not belong to this plugin.');
        }
        if (! in_array($plugin->status, ['installed', 'inactive', 'failed'], true)) {
            throw new RuntimeException('Deactivate the plugin and resolve any failed lifecycle state before restoring a version.');
        }

        $snapshot = $snapshotRecord->details['snapshot'] ?? null;
        if (! is_array($snapshot)) {
            throw new RuntimeException('The selected version snapshot is incomplete.');
        }

        $required = ['name', 'version', 'type', 'manifest', 'package_sha256', 'signature_key_id',
            'signature_verified_at', 'package_path', 'metadata'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $snapshot)) {
                throw new RuntimeException('The selected version snapshot is missing required restore metadata.');
            }
        }
        if (! is_array($snapshot['manifest']) || ! is_array($snapshot['metadata'])
            || ! is_string($snapshot['version']) || ! is_string($snapshot['package_path'])
            || ! is_string($snapshot['package_sha256']) || ! is_string($snapshot['signature_key_id'])) {
            throw new RuntimeException('The selected version snapshot contains invalid metadata.');
        }
        if (hash_equals($snapshot['version'], (string) $plugin->version)) {
            throw new RuntimeException('The selected version is already installed.');
        }

        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) throw new RuntimeException('The plugin identity is invalid.');
        $expectedPath = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$snapshot['version'];
        if (! hash_equals($expectedPath, $snapshot['package_path'])) {
            throw new RuntimeException('The restore package path does not match the plugin identity and version.');
        }

        // Verify the retained historical package and its detached signature before
        // changing the current database schema or registry pointer.
        $candidate = clone $plugin;
        $candidate->forceFill([
            'name' => $snapshot['name'],
            'version' => $snapshot['version'],
            'type' => $snapshot['type'],
            'manifest' => $snapshot['manifest'],
            'package_sha256' => $snapshot['package_sha256'],
            'signature_key_id' => $snapshot['signature_key_id'],
            'signature_verified_at' => $snapshot['signature_verified_at'],
            'package_path' => $snapshot['package_path'],
            'metadata' => $snapshot['metadata'],
            'status' => 'inactive',
        ]);
        if (! $this->signatureVerifier->verifyInstalledSignature($candidate)) {
            throw new RuntimeException('The historical release signature is unavailable; this version cannot be restored safely.');
        }
        $this->integrityVerifier->verify($candidate);

        $fromVersion = (string) $plugin->version;

        // Reverse only the current version's signed migrations. Any partial or
        // interrupted down() operation is blocked by PluginMigrationRunner and
        // requires manual database reconciliation; no registry switch occurs.
        if (! $this->signatureVerifier->verifyInstalledSignature($plugin)) {
            throw new RuntimeException('The current release signature cannot be re-verified; a safe restore point cannot be created.');
        }
        $this->integrityVerifier->verify($plugin);
        $this->history->recordSnapshot($plugin, $actor, [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'type' => $plugin->type,
            'manifest' => $plugin->manifest,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
            'package_path' => $plugin->package_path,
            'metadata' => $plugin->metadata,
            'installed_at' => $plugin->installed_at?->toISOString(),
        ]);
        $this->migrationRunner->rollback($plugin);

        DB::transaction(function () use ($plugin, $snapshot): void {
            $locked = PluginInstallation::query()->lockForUpdate()->findOrFail($plugin->getKey());
            if (! in_array($locked->status, ['installed', 'inactive', 'failed'], true)
                || (string) $locked->version !== (string) $plugin->version) {
                throw new RuntimeException('The plugin changed during restore; no version switch was applied.');
            }
            $locked->forceFill([
                'name' => $snapshot['name'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifest' => $snapshot['manifest'],
                'package_sha256' => $snapshot['package_sha256'],
                'signature_key_id' => $snapshot['signature_key_id'],
                'signature_verified_at' => $snapshot['signature_verified_at'],
                'package_path' => $snapshot['package_path'],
                'metadata' => $snapshot['metadata'],
                'installed_at' => $snapshot['installed_at'] ?? $locked->installed_at,
                'status' => 'inactive',
                'health_status' => 'unknown',
                'health_message' => 'A previous signed version was restored; run a health check before activation.',
            ])->save();
        });

        $restored = $plugin->fresh();
        try {
            // Re-apply only missing/previously rolled-back migrations for the
            // selected version. Completed migrations are idempotently skipped.
            $this->migrationRunner->migrate($restored);
        } catch (Throwable $exception) {
            $restored->forceFill([
                'status' => 'failed',
                'health_status' => 'failed',
                'health_message' => 'Version restore migration failed; manual database recovery is required.',
            ])->save();
            throw new RuntimeException('The previous package was selected but its migrations did not complete. Manual database recovery is required.', previous: $exception);
        }

        $restored->refresh();
        $this->history->record($restored, 'version_restored', $actor, [
            'from_version' => $fromVersion,
            'restored_snapshot_id' => $snapshotRecord->getKey(),
        ]);

        return $restored;
    }
}
