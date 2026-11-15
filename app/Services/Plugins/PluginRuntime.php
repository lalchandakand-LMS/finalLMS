<?php

namespace App\Services\Plugins;

use App\Contracts\Plugins\PluginInterface;
use App\Models\PluginInstallation;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class PluginRuntime
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginLifecycle $lifecycle,
    ) {
    }

    public function registerActivePlugins(): void
    {
        try {
            if (! Schema::hasTable('plugin_registry')) {
                return;
            }

            $plugins = PluginInstallation::query()
                ->where('status', 'active')
                ->orderBy('plugin_key')
                ->get();
        } catch (Throwable $exception) {
            // The application must still boot before the LMS database is installed.
            report($exception);
            return;
        }

        foreach ($plugins as $plugin) {
            try {
                $this->register($plugin);
            } catch (Throwable $exception) {
                report($exception);
                $this->disableAfterRuntimeFailure($plugin);
            }
        }
    }

    public function register(PluginInstallation $plugin): void
    {
        if ($plugin->status !== 'active') {
            throw new RuntimeException('Only an active plugin may enter the runtime.');
        }

        $trustedKeys = config('plugins.trusted_public_keys', []);
        if ($plugin->signature_verified_at === null
            || ! is_string($plugin->package_sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $plugin->package_sha256) !== 1
            || ! is_string($plugin->signature_key_id)
            || ! is_array($trustedKeys)
            || ! array_key_exists($plugin->signature_key_id, $trustedKeys)) {
            throw new RuntimeException('The active plugin does not have current trusted signature metadata.');
        }

        if (! is_array($plugin->manifest)
            || ! hash_equals((string) $plugin->plugin_key, (string) ($plugin->manifest['key'] ?? ''))
            || (string) $plugin->version !== (string) ($plugin->manifest['version'] ?? '')
            || (string) $plugin->name !== (string) ($plugin->manifest['name'] ?? '')
            || (string) $plugin->type !== (string) ($plugin->manifest['type'] ?? '')) {
            throw new RuntimeException('The active plugin manifest does not match its registry identity.');
        }

        $errors = $this->manifestValidator->errors($plugin->manifest);
        if ($errors !== []) {
            throw new RuntimeException('The active plugin manifest is invalid.');
        }

        // Reject modified, missing, extra, linked and out-of-root files before require().
        $this->integrityVerifier->verify($plugin);

        if (! str_ends_with(strtolower((string) $plugin->manifest['entrypoint']), '.php')) {
            throw new RuntimeException('The plugin entrypoint must be a PHP file.');
        }

        $pluginDirectory = realpath(base_path((string) $plugin->package_path));
        if ($pluginDirectory === false) {
            throw new RuntimeException('The active plugin directory is unavailable.');
        }

        $entrypointPath = $pluginDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $plugin->manifest['entrypoint']);
        $entrypointRoot = realpath($pluginDirectory);
        $entrypoint = realpath($entrypointPath);
        if ($entrypointRoot === false || $entrypoint === false || ! is_file($entrypoint) || is_link($entrypointPath)
            || ! str_starts_with($entrypoint, rtrim($entrypointRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The active plugin entrypoint is missing or unsafe.');
        }

        // Signed plugin PHP is trusted code, not sandboxed code. It runs in-process.
        $instance = (static fn (string $file): mixed => require $file)($entrypoint);
        if (! $instance instanceof PluginInterface) {
            throw new RuntimeException('The plugin entrypoint must return an implementation of PluginInterface.');
        }

        $instance->register(new PluginContext((string) $plugin->plugin_key, $pluginDirectory));

        $plugin->forceFill([
            'health_status' => 'healthy',
            'health_message' => null,
            'last_health_check_at' => now(),
        ])->save();
    }

    private function disableAfterRuntimeFailure(PluginInstallation $plugin): void
    {
        $current = $plugin->fresh();
        if (! $current || $current->status !== 'active') {
            return;
        }

        try {
            $this->lifecycle->transition((string) $current->plugin_key, 'inactive', [
                'health_status' => 'failed',
                'health_message' => 'Plugin runtime registration failed; plugin was automatically disabled.',
                'last_health_check_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $current->forceFill([
                'status' => 'inactive',
                'health_status' => 'failed',
                'health_message' => 'Plugin runtime registration failed; plugin was automatically disabled.',
                'last_health_check_at' => now(),
            ])->save();
        }
    }
}
