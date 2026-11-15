<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PluginLifecycle
{
    private const TRANSITIONS = [
        'discovered' => ['validated', 'failed'],
        'validated' => ['staged', 'failed'],
        'staged' => ['failed'],
        'installed' => ['active', 'inactive', 'update_staged', 'failed'],
        'active' => ['inactive', 'update_staged', 'failed'],
        'inactive' => ['active', 'update_staged', 'uninstalled', 'failed'],
        'update_staged' => ['active', 'inactive', 'failed'],
        'failed' => ['inactive', 'uninstalled'],
        'uninstalled' => [],
    ];

    public function __construct(
        private readonly PluginIntegrityVerifier $integrityVerifier,
    ) {
    }

    /**
     * Mark a staged package installed only after verifying the exact extracted file set.
     *
     * @param array<string, string> $fileHashes Relative path => SHA-256.
     */
    public function markInstalled(string $pluginKey, array $fileHashes): PluginInstallation
    {
        return DB::transaction(function () use ($pluginKey, $fileHashes): PluginInstallation {
            $plugin = PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($plugin->status !== 'staged' || ! $this->hasVerifiedPackage($plugin)) {
                throw new LogicException('A plugin cannot be installed without a verified signed package.');
            }

            $entrypoint = is_array($plugin->manifest) ? ($plugin->manifest['entrypoint'] ?? null) : null;
            if (! is_string($entrypoint) || ! array_key_exists($entrypoint, $fileHashes)) {
                throw new LogicException('The installed file manifest must include the signed plugin entrypoint.');
            }

            $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
            $metadata['integrity_version'] = 1;
            $metadata['installed_file_hashes'] = $fileHashes;

            $plugin->metadata = $metadata;
            $plugin->status = 'installed';
            if ($plugin->installed_at === null) {
                $plugin->installed_at = now();
            }

            // Verify against the actual final directory before persisting installed state.
            $this->integrityVerifier->verify($plugin);

            $plugin->save();

            return $plugin->refresh();
        });
    }

    /**
     * Apply an audited lifecycle state change. This service never loads plugin code.
     *
     * @param array<string, mixed> $attributes
     */
    public function transition(string $pluginKey, string $target, array $attributes = []): PluginInstallation
    {
        if (! array_key_exists($target, self::TRANSITIONS)) {
            throw new InvalidArgumentException('Unknown plugin lifecycle state.');
        }

        $allowedAttributes = [
            'health_status',
            'health_message',
            'last_health_check_at',
        ];

        if (array_diff(array_keys($attributes), $allowedAttributes) !== []) {
            throw new InvalidArgumentException('Unsupported plugin registry attribute.');
        }

        return DB::transaction(function () use ($pluginKey, $target, $attributes): PluginInstallation {
            $plugin = PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $plugin->status;
            if (! in_array($target, self::TRANSITIONS[$current] ?? [], true)) {
                throw new LogicException("Invalid plugin lifecycle transition: {$current} -> {$target}.");
            }

            if ($target === 'active') {
                if (! $this->hasVerifiedPackage($plugin)) {
                    throw new LogicException('A plugin cannot be activated without a verified signed package.');
                }

                $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
                if (($metadata['integrity_version'] ?? null) !== 1
                    || ! is_array($metadata['installed_file_hashes'] ?? null)) {
                    throw new LogicException('A plugin cannot be activated without an installed file integrity record.');
                }

                $this->integrityVerifier->verify($plugin);
            }

            $plugin->fill($attributes);
            $plugin->status = $target;

            if ($target === 'active') {
                $plugin->activated_at = now();
            }

            $plugin->save();

            return $plugin->refresh();
        });
    }

    private function hasVerifiedPackage(PluginInstallation $plugin): bool
    {
        $checksum = $plugin->package_sha256;
        $path = $plugin->package_path;
        $keyId = $plugin->signature_key_id;
        $trustedKeys = config('plugins.trusted_public_keys', []);

        return $plugin->signature_verified_at !== null
            && is_string($checksum)
            && preg_match('/^[a-f0-9]{64}$/D', $checksum) === 1
            && is_string($path)
            && $this->isCanonicalPluginPath($plugin, $path)
            && is_string($keyId)
            && trim($keyId) !== ''
            && is_array($trustedKeys)
            && array_key_exists($keyId, $trustedKeys);
    }

    private function isCanonicalPluginPath(PluginInstallation $plugin, string $path): bool
    {
        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;

        return hash_equals($expected, $path) && $this->isPrivatePluginPath($path);
    }

    private function isPrivatePluginPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || str_contains($path, ':') || str_contains($path, "\0")) {
            return false;
        }

        $segments = explode('/', $path);

        if (count($segments) !== 4 || $segments[0] !== 'plugins') {
            return false;
        }

        foreach (array_slice($segments, 1) as $segment) {
            if (preg_match('/^[A-Za-z0-9_.+-]+$/D', $segment) !== 1
                || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
