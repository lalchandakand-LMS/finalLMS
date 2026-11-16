<?php

namespace App\Http\Middleware;

use App\Models\PluginInstallation;
use Closure;
use Illuminate\Http\Request;

final class EnsurePluginPermission
{
    public function handle(Request $request, Closure $next, string $pluginKey, string $permission): mixed
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $pluginKey)->first();

        abort_unless(
            $plugin
                && $plugin->status === 'active'
                && in_array($permission, $plugin->manifest['permissions'] ?? [], true)
                && in_array($permission, $plugin->metadata['granted_permissions'] ?? [], true),
            403,
            'The plugin permission has not been granted by an administrator.'
        );

        return $next($request);
    }
}
