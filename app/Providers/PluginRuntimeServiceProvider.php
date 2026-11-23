<?php

namespace App\\Providers;

use App\\Models\\PluginInstallation;
use App\\Services\\Plugins\\PluginRuntime;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Support\\ServiceProvider;
use RuntimeException;
use Throwable;

final class PluginRuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginHookRegistry::class);
        $this->app->singleton(PluginRuntime::class);
    }

    public function boot(): void
    {
        // Resolve the database-backed plugin registry only after every framework
        // provider has registered its services (translator, exception handler, etc.).
        // This keeps first-run and error responses safe during early application boot.
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

        $this->app->make(PluginRuntime::class)->bootActivePlugins();
    }
}
