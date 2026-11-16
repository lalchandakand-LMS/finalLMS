<?php

namespace App\Contracts\Plugins;

use App\Services\Plugins\PluginContext;

interface PluginContract
{
    /**
     * Register the plugin's services and portal-scoped routes.
     * This method runs during application provider registration.
     */
    public function register(PluginContext $context): void;

    /**
     * Boot plugin integrations after all providers have registered.
     */
    public function boot(PluginContext $context): void;
}
