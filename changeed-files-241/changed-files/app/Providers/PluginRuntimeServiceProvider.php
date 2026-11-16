<?php

namespace App\Providers;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginCompatibilityChecker;
use App\Services\Plugins\PluginHookRegistry;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

final class PluginRuntimeServiceProvider extends ServiceProvider
{
    private bool $runtimeAvailable = false;

    public function register(): void
    {
        $this->app->singleton(PluginCompatibilityChecker::class);
        $this->app->singleton(PluginHookRegistry::class);
        $this->app->singleton(PluginRuntime::class);

        // Runtime code is loaded only for web requests, never during install/build CLI commands.
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            if (! Schema::hasTable('plugin_registry')) {
                return;
            }
        } catch (Throwable) {
            // The installer and first-run setup must remain available before database initialization.
            return;
        }

        if ($this->app->routesAreCached()
            && PluginInstallation::query()->where('status', 'active')->exists()) {
            throw new RuntimeException('Active plugin routes cannot run from a cached route file. Run route:clear before enabling plugin runtime.');
        }

        $this->app->make(PluginRuntime::class)->registerActivePlugins();
        $this->runtimeAvailable = true;
    }

    public function boot(): void
    {
        if ($this->runtimeAvailable) {
            $this->app->make(PluginRuntime::class)->bootActivePlugins();
        }
    }
}
