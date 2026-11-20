<?php

namespace App\Http\Middleware;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginSignatureVerifier;
use Closure;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Drop queued work when its owning signed plugin is no longer trusted, active,
 * intact, or explicitly authorized to perform the requested capability.
 */
final class EnsurePluginJobActive
{
    public function __construct(
        private readonly string $pluginKey,
        private readonly string $permission,
    ) {}

    public function handle(object $job, Closure $next): mixed {
        // Infrastructure failures must bubble to the queue worker for retry.
        if (! Schema::hasTable('plugin_registry')) {
            throw new RuntimeException('The plugin registry is unavailable; queued plugin work cannot be authorized.');
        }

        $plugin = PluginInstallation::query()
            ->where('plugin_key', $this->pluginKey)
            ->first();

        $manifestPermissions = is_array($plugin?->manifest)
            ? ($plugin->manifest['permissions'] ?? [])
            : [];
        $grantedPermissions = is_array($plugin?->metadata)
            ? ($plugin->metadata['granted_permissions'] ?? [])
            : [];

        $allowed = $plugin
            && $plugin->status === 'active'
            && $plugin->signature_verified_at !== null
            && is_array($manifestPermissions)
            && in_array($this->permission, $manifestPermissions, true)
            && is_array($grantedPermissions)
            && in_array($this->permission, $grantedPermissions, true);

        if (! $allowed) {
            // Confirmed inactive, missing or revoked plugin work is stale.
            return null;
        }

        try {
            // Resolve services from Laravel's container here: queue middleware
            // pipelines invoke handle($job, $next), not arbitrary method injection.
            $signatureVerifier = app(PluginSignatureVerifier::class);
            $integrityVerifier = app(PluginIntegrityVerifier::class);

            // Re-verify the detached Ed25519 signature against the current
            // trusted key and verify every installed file before handler code runs.
            if (! $signatureVerifier->verifyInstalledSignature($plugin)) {
                return null; // Legacy unsigned records are not trusted for new jobs.
            }

            $integrityVerifier->verify($plugin);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            report($exception);

            // Invalid signatures, revoked keys and tampered packages are
            // permanent authorization failures, not transient queue outages.
            return null;
        }

        return $next($job);
    }
}
