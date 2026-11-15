<?php

namespace App\\Services\\Plugins;

use App\\Models\\PluginInstallation;
use App\\Models\\PluginVersionRecord;
use RuntimeException;
use Throwable;

final class PluginRollbackService
{
    public function __construct(
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginLifecycle $lifecycle,
    ) {
    }

    public function rollback(PluginInstallation $plugin, string $targetVersion): PluginInstallation
    {
        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());

        if ($plugin->status !== 'inactive') {
            throw new RuntimeException('Deactivate the plugin before rolling back its version.');
        }

        if (! preg_match('/^\\d+\\.\\d+\\.\\d+(?:[-+][A-Za-z0-9.-]+)?$/D', $targetVersion)
            || hash_equals((string) $plugin->version, $targetVersion)) {
            throw new RuntimeException('Choose a different, valid historical plugin version.');
        }

        $record = PluginVersionRecord::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('version', $targetVersion)
            ->first();

        if (! $record) {
            throw new RuntimeException('The requested version is not present in immutable plugin history.');
        }

        $history = is_array($record->metadata) ? $record->metadata : [];
        $fileHashes = $history['installed_file_hashes'] ?? null;
        $verifiedAt = $history['signature_verified_at'] ?? null;

        if (($history['integrity_version'] ?? null) !== 1
            || ! is_array($fileHashes) || $fileHashes === []
            || ! is_string($verifiedAt) || strtotime($verifiedAt) === false) {
            throw new RuntimeException('This historical version has no complete integrity evidence and cannot be rolled back safely.');
        }

        $candidate = clone $plugin;
        $candidate->version = $record->version;
        $candidate->package_path = $record->package_path;
        $candidate->package_sha256 = $record->package_sha256;
        $candidate->signature_key_id = $record->signature_key_id;
        $candidate->signature_verified_at = $verifiedAt;
        $candidate->metadata = [
            'integrity_version' => 1,
            'installed_file_hashes' => $fileHashes,
            'rolled_back_from' => $plugin->version,
            'rollback_verified_at' => now()->toISOString(),
        ];

        // Historical files must still match the immutable recorded hashes before pointer change.
        $this->integrityVerifier->verify($candidate);

        $previous = [
            'version' => $plugin->version,
            'package_path' => $plugin->package_path,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'signature_verified_at' => $plugin->signature_verified_at,
            'metadata' => $plugin->metadata,
        ];

        $this->lifecycle->transition((string) $plugin->plugin_key, 'update_staged');

        try {
            return $this->lifecycle->transition((string) $plugin->plugin_key, 'inactive', [
                'version' => $candidate->version,
                'package_path' => $candidate->package_path,
                'package_sha256' => $candidate->package_sha256,
                'signature_key_id' => $candidate->signature_key_id,
                'signature_verified_at' => $candidate->signature_verified_at,
                'metadata' => $candidate->metadata,
            ]);
        } catch (Throwable $exception) {
            $current = PluginInstallation::query()->find($plugin->getKey());
            if ($current?->status === 'update_staged') {
                try {
                    $this->lifecycle->transition((string) $plugin->plugin_key, 'inactive', $previous);
                } catch (Throwable) {
                    // Keep the original exception; the staged state remains visible for admin recovery.
                }
            }

            throw $exception;
        }
    }
}
