<?php

namespace App\Providers;

use App\Services\Plugins\PluginRuntime;
use Illuminate\Support\ServiceProvider;

class PluginRuntimeServiceProvider extends ServiceProvider
{
    public function boot(PluginRuntime $runtime): void
    {
        $runtime->registerActivePlugins();
    }
}
