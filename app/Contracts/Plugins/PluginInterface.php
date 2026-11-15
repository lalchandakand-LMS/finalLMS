<?php

namespace App\Contracts\Plugins;

use App\Services\Plugins\PluginContext;

interface PluginInterface
{
    /**
     * Register this trusted plugin's web/API routes and approved extension hooks.
     * Implementations execute inside the LMS PHP process and are not sandboxed.
     */
    public function register(PluginContext $context): void;
}
