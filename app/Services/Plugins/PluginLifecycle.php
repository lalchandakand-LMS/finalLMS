<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PluginLifecycle
{
    public function __construct(private readonly PluginTreeHasher $treeHasher)
    {
    }

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


    /**
     * Mark a staged package installed only after hashing the actual final files.
     * Generic lifecycle transitions cannot set installed state or forge this digest.
     */
    public function markInstalled(string $pluginKey): PluginInstallation
    {
        return DB::transaction(function () use ($pluginKey): PluginInstallation {
            $plugin = PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($plugin->status !== 'staged' || ! $this->hasVerifiedPackage($plugin, [])) {
                throw new LogicException('A plugin cannot be installed without a verified signed package.');
            }

            $parts = explode('.', $pluginKey, 2);
            $vendor = $parts[0];
            $slug = str_replace('.', '-', $parts[1]);
            $pluginsPath = base_path('plugins');
            $versionPath = $pluginsPath.'/'.$vendor.'/'.$slug.'/'.$plugin->version;

            if (is_link($pluginsPath) || is_link($pluginsPath.'/'.$vendor)
                || is_link($pluginsPath.'/'.$vendor.'/'.$slug) || is_link($versionPath)) {
                throw new LogicException('Symbolic links are forbidden in plugin installation paths.');
            }

            $pluginsRoot = realpath($pluginsPath);
            $installedPath = realpath($versionPath);
            if ($pluginsRoot === false || $installedPath === false
                || ! str_starts_with($installedPath, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new LogicException('The verified plugin package is not present in its canonical private directory.');
            }

            $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
            $metadata['installed_tree_sha256'] = $this->treeHasher->hash($installedPath);
            $plugin->metadata = $metadata;
            $plugin->status = 'installed';
            if ($plugin->installed_at === null) {
                $plugin->installed_at = now();
            }
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
            'health_message', 'last_health_check_at',
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

            if ($target === 'active' && ! $this->hasVerifiedPackage($plugin, $attributes)) {
                throw new LogicException('A plugin cannot be activated without a verified signed package.');
            }

            if ($target === 'active' && ! $this->hasInstalledTreeHash($plugin)) {
                throw new LogicException('A plugin cannot be activated without an installed file integrity record.');
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

    private function hasInstalledTreeHash(PluginInstallation $plugin): bool
    {
        $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
        $treeHash = $metadata['installed_tree_sha256'] ?? null;

        return is_string($treeHash) && preg_match('/^[a-f0-9]{64}$/D', $treeHash) === 1;
    }

    private function hasVerifiedPackage(PluginInstallation $plugin, array $attributes): bool
    {
        $checksum = $attributes['package_sha256'] ?? $plugin->package_sha256;
        $path = $attributes['package_path'] ?? $plugin->package_path;
        $keyId = $attributes['signature_key_id'] ?? $plugin->signature_key_id;

        return $plugin->signature_verified_at !== null
            && is_string($checksum)
            && preg_match('/^[a-f0-9]{64}$/iD', $checksum) === 1
            && is_string($path)
            && $this->isCanonicalPluginPath($plugin, $path)
            && is_string($keyId)
            && trim($keyId) !== ''
            && is_array(config('plugins.trusted_public_keys', []))
            && array_key_exists($keyId, config('plugins.trusted_public_keys', []));
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
