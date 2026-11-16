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
    ) {}

    /**
     * Load only active, trusted, integrity-verified packages from the private plugins directory.
     * Signed plugin code is trusted application code, not sandboxed code.
     */
    public function registerActivePlugins(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        foreach (PluginInstallation::query()->where('status', 'active')->orderBy('plugin_key')->get() as $plugin) {
            try {
                $instance = $this->loadVerifiedEntrypoint($plugin);
                $context = new PluginContext($this->app, $this->router, $plugin);

                $this->loaded[$plugin->plugin_key] = [
                    'plugin' => $plugin,
                    'instance' => $instance,
                    'context' => $context,
                ];

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

                $loaded['instance']->boot($loaded['context']);
            } catch (Throwable $exception) {
                report($exception);
                $this->markFailed($loaded['plugin']);
                unset($this->loaded[$key]);
            }
        }
    }

    private function loadVerifiedEntrypoint(PluginInstallation $plugin): PluginContract
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

        $instance = require $realEntrypoint;
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
