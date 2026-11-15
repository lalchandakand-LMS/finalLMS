<?php

namespace App\Plugins;

/**
 * Stable lifecycle contract for reviewed first-party plugins.
 *
 * Plugin implementations must be registered by trusted application code.
 * Never instantiate a class name supplied by an uploaded manifest.
 */
interface PluginContract
{
    public function register(): void;

    public function boot(): void;
}
