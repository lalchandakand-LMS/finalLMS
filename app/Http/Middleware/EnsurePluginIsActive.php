<?php

namespace App\Http\Middleware;

use App\Models\PluginInstallation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsurePluginIsActive
{
    public function handle(Request $request, Closure $next, string $pluginKey): Response
    {
        if (! Schema::hasTable('plugin_registry')
            || ! PluginInstallation::query()
                ->where('plugin_key', $pluginKey)
                ->where('status', 'active')
                ->exists()) {
            abort(404);
        }

        return $next($request);
    }
}
