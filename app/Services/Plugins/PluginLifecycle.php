<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PluginLifecycle
{
    public function __construct(private readonly PluginVersionHistory $versionHistory)
    {
    }

    private const TRANSITIONS = [
        'discovered' => ['validated', 'failed'],
        'validated' => ['staged', 'failed'],
        'staged' => ['installed', 'failed'],
        'installed' => ['active', 'inactive', 'update_staged', 'failed'],
        'active' => ['inactive', 'update_staged', 'failed'],
        'inactive' => ['active', 'update_staged', 'uninstalled', 'failed'],
        'update_staged' => ['active', 'inactive', 'failed'],
        'failed' => ['inactive', 'uninstalled'],
        'uninstalled' => ['inactive'],
    ];

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
            'health_message', 'last_health_check_at', 'metadata',
            'version', 'package_sha256', 'signature_key_id', 'package_path', 'signature_verified_at',
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

            if (in_array($target, ['installed', 'active'], true) && ! $this->hasVerifiedPackage($plugin, $attributes)) {
                throw new LogicException('A plugin cannot be installed or activated without a verified signed package.');
            }

            $plugin->fill($attributes);
            $plugin->status = $target;

            if ($target === 'active') {
                $plugin->activated_at = now();
            }

            if ($target === 'installed' && $plugin->installed_at === null) {
                $plugin->installed_at = now();
            }

            $plugin->save();
            $plugin = $plugin->refresh();

            if ($target === 'installed') {
                $this->versionHistory->recordInstalledVersion($plugin);
            }

            return $plugin->refresh();
        });
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
            && $this->isPrivatePluginPath($path)
            && is_string($keyId)
            && trim($keyId) !== '';
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
