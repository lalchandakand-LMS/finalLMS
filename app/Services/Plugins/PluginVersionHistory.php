<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use App\Models\PluginVersionRecord;
use RuntimeException;

final class PluginVersionHistory
{
    public function recordInstalledVersion(PluginInstallation $plugin): PluginVersionRecord
    {
        if (! in_array($plugin->status, ['installed', 'active', 'inactive'], true)
            || $plugin->signature_verified_at === null
            || ! is_string($plugin->package_sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $plugin->package_sha256) !== 1
            || ! is_string($plugin->signature_key_id)
            || ! is_string($plugin->package_path)) {
            throw new RuntimeException('Only a verified installed package can be added to version history.');
        }

        $existing = PluginVersionRecord::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('version', $plugin->version)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->package_sha256, $plugin->package_sha256)
                || ! hash_equals($existing->signature_key_id, $plugin->signature_key_id)
                || ! hash_equals($existing->package_path, $plugin->package_path)) {
                throw new RuntimeException('A published plugin version is immutable and cannot be replaced.');
            }

            return $existing;
        }

        return PluginVersionRecord::query()->create([
            'plugin_key' => $plugin->plugin_key,
            'version' => $plugin->version,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'package_path' => $plugin->package_path,
            'status' => $plugin->status,
            'installed_at' => $plugin->installed_at ?? now(),
            'metadata' => [
                'integrity_version' => data_get($plugin->metadata, 'integrity_version'),
                'installed_file_hashes' => data_get($plugin->metadata, 'installed_file_hashes', []),
                'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
                'file_count' => count(data_get($plugin->metadata, 'installed_file_hashes', [])),
            ],
        ]);
    }
}
