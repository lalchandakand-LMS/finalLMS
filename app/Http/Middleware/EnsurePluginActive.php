<?php

namespace App\Http\Middleware;

use App\Models\PluginInstallation;
use Closure;
use Illuminate\Http\Request;

final class EnsurePluginActive
{
    public function handle(Request $request, Closure $next, string $pluginKey): mixed
    {
        $plugin = PluginInstallation::query()
            ->where('plugin_key', $pluginKey)
            ->first();

        $trustedKeys = config('plugins.trusted_public_keys', []);
        $trusted = is_array($trustedKeys)
            && is_string($plugin?->signature_key_id)
            && array_key_exists($plugin->signature_key_id, $trustedKeys);

        abort_unless(
            $plugin
                && $plugin->status === 'active'
                && $plugin->signature_verified_at !== null
                && $trusted,
            404,
            'This plugin is not active.'
        );

        return $next($request);
    }
}
