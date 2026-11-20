<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

final class PluginHookRegistry
{
    /** @var array<string, list<array{plugin_key: string, priority: int, callback: callable}>> */
    private array $hooks = [];

    /** @var array<string, list<array{plugin_key: string, label: string, route: string, icon: string, permission: ?string}>> */
    private array $portalNav = [];

    /** @var array<string, list<string>> */
    private array $capabilities = [];

    public function registerHook(string $pluginKey, string $hook, callable $callback, int $priority = 10): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]{1,120}$/D', $hook) !== 1) {
            throw new InvalidArgumentException('Invalid plugin hook identifier.');
        }

        $this->hooks[$hook][] = [
            'plugin_key' => $pluginKey,
            'priority' => $priority,
            'callback' => $callback,
        ];
    }

    /**
     * @param  array{icon?: string, permission?: ?string}  $options
     */
    public function registerPortalNavigation(
        string $pluginKey,
        string $portal,
        string $label,
        string $routeName,
        array $options = [],
    ): void {
        if (! in_array($portal, ['admin', 'branch', 'teacher', 'student', 'guardian', 'support_staff', 'public'], true)
            || trim($label) === ''
            || preg_match('/^[a-z][a-z0-9_.-]{1,120}$/D', $routeName) !== 1) {
            throw new InvalidArgumentException('Invalid plugin navigation registration.');
        }

        $this->portalNav[$portal][] = [
            'plugin_key' => $pluginKey,
            'label' => mb_substr(trim($label), 0, 80),
            'route' => $routeName,
            'icon' => mb_substr((string) ($options['icon'] ?? '🧩'), 0, 16),
            'permission' => isset($options['permission']) && is_string($options['permission'])
                ? $options['permission']
                : null,
        ];
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function registerCapabilities(string $pluginKey, array $capabilities): void
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $pluginKey)->first();
        $declared = $plugin && is_array($plugin->manifest['capabilities'] ?? null)
            ? $plugin->manifest['capabilities']
            : [];

        $clean = [];
        foreach ($capabilities as $capability) {
            if (is_string($capability)
                && trim($capability) !== ''
                && in_array(trim($capability), $declared, true)) {
                $clean[] = trim($capability);
            }
        }
        $this->capabilities[$pluginKey] = array_values(array_unique($clean));
    }

    /**
     * Execute hooks registered by currently active, signature-verified plugins.
     *
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    public function dispatch(string $hook, array $payload = []): array
    {
        $entries = $this->hooks[$hook] ?? [];
        if ($entries === []) {
            return [];
        }

        usort($entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $activeKeys = $this->activeVerifiedPlugins();
        $results = [];

        foreach ($entries as $entry) {
            if (! isset($activeKeys[$entry['plugin_key']])) {
                continue;
            }

            try {
                $value = ($entry['callback'])($payload);
                if ($value !== null) {
                    $results[] = $value;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $results;
    }

    /**
     * Return navigation links for a given portal belonging strictly to currently active plugins.
     *
     * @return list<array{plugin_key: string, label: string, route: string, icon: string, permission: ?string}>
     */
    public function portalNavigation(string $portal): array
    {
        $items = $this->portalNav[$portal] ?? [];
        if ($items === []) {
            return [];
        }

        $activePlugins = $this->activeVerifiedPlugins();
        $visible = [];

        foreach ($items as $item) {
            $plugin = $activePlugins[$item['plugin_key']] ?? null;
            if (! $plugin instanceof PluginInstallation) {
                continue;
            }

            if (! in_array($portal, $plugin->manifest['portals'] ?? [], true)) {
                continue;
            }

            if (! Route::has($item['route'])) {
                continue;
            }

            $grantedPermissions = is_array($plugin->metadata)
                && is_array($plugin->metadata['granted_permissions'] ?? null)
                ? $plugin->metadata['granted_permissions']
                : [];
            $permission = $item['permission'];
            if ($portal !== 'public'
                && (! is_string($permission) || ! in_array($permission, $grantedPermissions, true))) {
                continue;
            }
            if ($permission !== null && ! in_array($permission, $grantedPermissions, true)) {
                continue;
            }

            $visible[] = $item;
        }

        return $visible;
    }

    public function hasCapability(string $capability): bool
    {
        return $this->pluginsWithCapability($capability) !== [];
    }

    /**
     * @return list<string>
     */
    public function pluginsWithCapability(string $capability): array
    {
        $activeKeys = $this->activeVerifiedPlugins();
        $matched = [];

        foreach ($this->capabilities as $pluginKey => $caps) {
            if (isset($activeKeys[$pluginKey]) && in_array($capability, $caps, true)) {
                $matched[] = $pluginKey;
            }
        }

        return $matched;
    }

    public function reset(): void
    {
        $this->hooks = [];
        $this->portalNav = [];
        $this->capabilities = [];
    }

    /**
     * @return array<string, PluginInstallation>
     */
    private function activeVerifiedPlugins(): array
    {
        try {
            if (! Schema::hasTable('plugin_registry')) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $signatureVerifier = app(PluginSignatureVerifier::class);
        $integrityVerifier = app(PluginIntegrityVerifier::class);
        $active = [];

        foreach (PluginInstallation::query()->where('status', 'active')->get() as $plugin) {
            try {
                // A persisted "verified" timestamp and a still-present key ID are
                // not proof that the current trusted key still validates the release.
                if (! $signatureVerifier->verifyInstalledSignature($plugin)) {
                    continue;
                }

                // Hooks and portal navigation execute in the current process after
                // boot; re-check installed files so tampering/revocation takes effect.
                $integrityVerifier->verify($plugin);
                $active[$plugin->plugin_key] = $plugin;
            } catch (PluginTrustFailure $exception) {
                report($exception);
                // Confirmed trust/integrity failures must fail closed.
                continue;
            }
        }

        return $active;
    }
}
