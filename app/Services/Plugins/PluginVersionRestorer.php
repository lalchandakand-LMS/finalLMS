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
        private readonly PluginLifecycle $lifecycle,
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
        if (! is_array($snapshot)) throw new RuntimeException('The selected version snapshot is incomplete.');

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

        // Verify the target release and all its files before changing the current
        // schema or registry pointer.
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

        if (! $this->signatureVerifier->verifyInstalledSignature($plugin)) {
            throw new RuntimeException('The current release signature cannot be re-verified; a safe restore point cannot be created.');
        }
        $this->integrityVerifier->verify($plugin);

        $fromVersion = (string) $plugin->version;
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

        // Serialize update/restore operations. Keep the registry in a non-runnable
        // state while migrations are reversed and the selected release is applied.
        $this->lifecycle->transition((string) $plugin->plugin_key, 'update_staged');

        try {
            $currentMigrationPackage = clone $plugin;
            $currentMigrationPackage->status = 'inactive';
            $this->migrationRunner->rollback($currentMigrationPackage);

            DB::transaction(function () use ($plugin, $snapshot): void {
                $locked = PluginInstallation::query()->lockForUpdate()->findOrFail($plugin->getKey());
                if ($locked->status !== 'update_staged'
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
                    'status' => 'update_staged',
                    'health_status' => 'unknown',
                    'health_message' => 'A signed version restore is in progress.',
                ])->save();
            });

            $restored = $plugin->fresh();
            $migrationPackage = clone $restored;
            $migrationPackage->status = 'inactive';
            $this->migrationRunner->migrate($migrationPackage);

            $this->lifecycle->transition((string) $plugin->plugin_key, 'inactive', [
                'health_status' => 'unknown',
                'health_message' => 'A previous signed version was restored; run a health check before activation.',
            ]);
        } catch (Throwable $exception) {
            $fresh = PluginInstallation::query()->find($plugin->getKey());
            if ($fresh && $fresh->status === 'update_staged') {
                try {
                    $this->lifecycle->transition((string) $plugin->plugin_key, 'failed', [
                        'health_status' => 'failed',
                        'health_message' => 'Version restore did not complete; inspect migration state and perform manual database recovery if required.',
                    ]);
                } catch (Throwable $stateException) {
                    report($stateException);
                }
            }
            throw new RuntimeException(
                'Version restore did not complete. The plugin was left inactive or failed; inspect server logs and database state.',
                previous: $exception,
            );
        }

        $restored = $plugin->fresh();
        $this->history->record($restored, 'version_restored', $actor, [
            'from_version' => $fromVersion,
            'restored_snapshot_id' => $snapshotRecord->getKey(),
        ]);

        return $restored;
    }
}
