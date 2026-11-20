<?php

namespace App\Services\Plugins;

use App\Contracts\Plugins\PluginContract;
use App\Models\PluginInstallation;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Throwable;
use RuntimeException;

final class PluginRuntime
{
    /** @var array<string, array{plugin:PluginInstallation,instance:PluginContract,context:PluginContext}> */
    private array $loaded = [];

    private bool $registered = false;
    private bool $booted = false;

    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginSignatureVerifier $signatureVerifier,
        private readonly PluginLifecycle $lifecycle,
        private readonly ?PluginHookRegistry $hookRegistry = null,
    ) {}

    /**
     * Verify every active package before executing any plugin PHP, then load
     * packages in dependency order. This prevents a dependent entrypoint from
     * require_once-loading an unverified dependency before that dependency's
     * signature and installed-file integrity checks have run.
     */
    public function registerActivePlugins(): void
    {
        if ($this->registered) {
            return;
        }
        $hooks = $this->hookRegistry ?? $this->app->make(PluginHookRegistry::class);
        // Do not mark registration complete until the registry query succeeds.
        // A transient database outage must allow a long-lived worker to retry.
        $active = PluginInstallation::query()->where('status', 'active')->orderBy('plugin_key')->get();
        $this->registered = true;

        /** @var array<string, array{plugin:PluginInstallation,entrypoint:string}> $verified */
        $verified = [];

        // Phase one: signature, manifest identity, path and complete file-integrity
        // verification for every active package. No entrypoint is required here.
        foreach ($active as $plugin) {
            try {
                $verified[$plugin->plugin_key] = [
                    'plugin' => $plugin,
                    'entrypoint' => $this->verifiedEntrypointPath($plugin),
                ];
            } catch (Throwable $exception) {
                report($exception);
                $this->markFailed($plugin);
            }
        }

        // Phase two: dependencies must themselves be active and verified.
        // Topological ordering ensures dependency entrypoints run before dependents.
        $orderedKeys = $this->dependencyOrder($verified);

        foreach ($orderedKeys as $key) {
            $record = $verified[$key];
            $plugin = $record['plugin'];
            $dependencies = $plugin->manifest['dependencies'] ?? [];

            $dependenciesLoaded = true;
            foreach ($dependencies as $dependency) {
                if (! isset($this->loaded[$dependency])) {
                    $dependenciesLoaded = false;
                    break;
                }
            }

            if (! $dependenciesLoaded) {
                $this->markFailed($plugin);
                continue;
            }

            try {
                $instance = $this->instantiateVerifiedEntrypoint($record['entrypoint']);
                $context = new PluginContext($this->app, $this->router, $plugin, $hooks);

                $this->loaded[$plugin->plugin_key] = [
                    'plugin' => $plugin,
                    'instance' => $instance,
                    'context' => $context,
                ];

                $hooks->registerCapabilities(
                    (string) $plugin->plugin_key,
                    is_array($plugin->manifest['capabilities'] ?? null) ? $plugin->manifest['capabilities'] : [],
                );
                $instance->register($context);
            } catch (Throwable $exception) {
                unset($this->loaded[$plugin->plugin_key]);
                report($exception);
                $this->markFailed($plugin);
            }
        }
    }

    public function bootActivePlugins(): void
    {
        if ($this->booted) {
            return;
        }

        $this->registerActivePlugins();
        $this->booted = true;

        foreach ($this->loaded as $key => $loaded) {
            try {
                $current = PluginInstallation::query()->find($loaded['plugin']->getKey());
                if (! $current || $current->status !== 'active') {
                    unset($this->loaded[$key]);
                    continue;
                }

                // A dependency may have failed while registering or booting.
                foreach (($current->manifest['dependencies'] ?? []) as $dependency) {
                    if (! isset($this->loaded[$dependency])) {
                        throw new RuntimeException('A required plugin dependency did not remain available during boot: '.$dependency);
                    }
                }

                $loaded['instance']->boot($loaded['context']);
            } catch (Throwable $exception) {
                report($exception);
                $this->markFailed($loaded['plugin']);
                unset($this->loaded[$key]);
            }
        }
    }

    /**
     * @param array<string, array{plugin:PluginInstallation,entrypoint:string}> $verified
     * @return list<string>
     */
    private function dependencyOrder(array $verified): array
    {
        $states = [];
        $failed = [];
        $ordered = [];

        $visit = function (string $key) use (&$visit, &$states, &$failed, &$ordered, $verified): bool {
            if (isset($failed[$key])) {
                return false;
            }
            if (($states[$key] ?? 0) === 2) {
                return true;
            }
            if (($states[$key] ?? 0) === 1) {
                // A dependency cycle is not loadable. Mark the current node;
                // callers in the cycle are marked while the failure propagates.
                $failed[$key] = true;
                return false;
            }

            $states[$key] = 1;
            $plugin = $verified[$key]['plugin'];

            foreach (($plugin->manifest['dependencies'] ?? []) as $dependency) {
                if (! isset($verified[$dependency]) || ! $visit($dependency)) {
                    $failed[$key] = true;
                    break;
                }
            }

            $states[$key] = 2;
            if (! isset($failed[$key])) {
                $ordered[] = $key;
                return true;
            }

            return false;
        };

        foreach (array_keys($verified) as $key) {
            $visit($key);
        }

        foreach (array_keys($failed) as $key) {
            if (isset($verified[$key])) {
                $this->markFailed($verified[$key]['plugin']);
            }
        }

        return $ordered;
    }

    private function verifiedEntrypointPath(PluginInstallation $plugin): string
    {
        if ($plugin->signature_verified_at === null
            || ! is_string($plugin->package_sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $plugin->package_sha256) !== 1
            || ! is_string($plugin->signature_key_id)
            || trim($plugin->signature_key_id) === '') {
            throw new RuntimeException('The active plugin is missing verified release metadata.');
        }

        $this->signatureVerifier->verifyInstalledSignature($plugin);

        $errors = $this->manifestValidator->errors($plugin->manifest ?? []);
        if ($errors !== []) {
            throw new RuntimeException('The active plugin manifest is invalid: '.implode(' ', $errors));
        }

        if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
            || (string) $plugin->name !== (string) $plugin->manifest['name']
            || (string) $plugin->version !== (string) $plugin->manifest['version']
            || (string) $plugin->type !== (string) $plugin->manifest['type']) {
            throw new RuntimeException('The active plugin manifest does not match its registry identity.');
        }

        $this->integrityVerifier->verify($plugin);

        $packageRoot = realpath(base_path((string) $plugin->package_path));
        if ($packageRoot === false || is_link(base_path((string) $plugin->package_path))) {
            throw new RuntimeException('The active plugin package directory is unavailable or unsafe.');
        }

        $entrypoint = $packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $plugin->manifest['entrypoint']);
        $realEntrypoint = realpath($entrypoint);
        $prefix = rtrim($packageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($realEntrypoint === false || ! is_file($realEntrypoint) || is_link($entrypoint)
            || ! str_starts_with($realEntrypoint, $prefix)) {
            throw new RuntimeException('The active plugin entrypoint is missing or resolves outside its package.');
        }

        return $realEntrypoint;
    }

    private function instantiateVerifiedEntrypoint(string $entrypoint): PluginContract
    {
        $instance = require $entrypoint;
        if (! $instance instanceof PluginContract) {
            throw new RuntimeException('A plugin entrypoint must return an instance implementing PluginContract.');
        }

        return $instance;
    }

    private function markFailed(PluginInstallation $plugin): void
    {
        try {
            $current = PluginInstallation::query()->find($plugin->getKey());
            if ($current && $current->status === 'active') {
                $this->lifecycle->transition((string) $current->plugin_key, 'failed', [
                    'health_status' => 'failed',
                    'health_message' => 'Plugin runtime verification or boot failed. Review server logs.',
                    'last_health_check_at' => now(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
