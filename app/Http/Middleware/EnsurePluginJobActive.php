<?php

namespace App\\Http\\Middleware;

use App\\Models\\PluginInstallation;
use Closure;
use Illuminate\\Support\\Facades\\Schema;

/**
 * Drop queued work when its owning signed plugin is no longer trusted, active,
 * or explicitly authorized to perform the requested background capability.
 */
final class EnsurePluginJobActive
{
    public function __construct(
        private readonly string $pluginKey,
        private readonly string $permission,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        try {
            if (! Schema::hasTable('plugin_registry')) {
                return null;
            }

            $plugin = PluginInstallation::query()
                ->where('plugin_key', $this->pluginKey)
                ->first();

            $trustedKeys = config('plugins.trusted_public_keys', []);
            $trustedKey = is_array($trustedKeys) && is_string($plugin?->signature_key_id)
                ? ($trustedKeys[$plugin->signature_key_id] ?? null)
                : null;

            $manifestPermissions = is_array($plugin?->manifest)
                ? ($plugin->manifest['permissions'] ?? [])
                : [];
            $grantedPermissions = is_array($plugin?->metadata)
                ? ($plugin->metadata['granted_permissions'] ?? [])
                : [];

            $allowed = $plugin
                && $plugin->status === 'active'
                && $plugin->signature_verified_at !== null
                && is_string($trustedKey)
                && trim($trustedKey) !== ''
                && is_array($manifestPermissions)
                && in_array($this->permission, $manifestPermissions, true)
                && is_array($grantedPermissions)
                && in_array($this->permission, $grantedPermissions, true);

            if (! $allowed) {
                // Returning without invoking the next pipe acknowledges and drops
                // stale work; retrying cannot make a deactivated plugin safe.
                return null;
            }
        } catch (\\Throwable $exception) {
            report($exception);

            // Fail closed if registry state cannot be established.
            return null;
        }

        return $next($job);
    }
}
