<?php

namespace App\Http\Middleware;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginSignatureVerifier;
use App\Services\Plugins\PluginTrustFailure;
use Closure;
use Illuminate\Http\Request;

final class EnsurePluginActive
{
    public function __construct(
        private readonly PluginSignatureVerifier $signatureVerifier,
        private readonly PluginIntegrityVerifier $integrityVerifier,
    ) {}

    public function handle(Request $request, Closure $next, string $pluginKey): mixed
    {
        $plugin = PluginInstallation::query()
            ->where('plugin_key', $pluginKey)
            ->first();

        abort_unless($plugin && $plugin->status === 'active', 404, 'This plugin is not active.');

        try {
            // Routes can autoload plugin controllers after runtime registration.
            // Re-check the current signing key and every package file before the
            // request is allowed to reach plugin code.
            if (! $this->signatureVerifier->verifyInstalledSignature($plugin)) {
                abort(404, 'This plugin release is not trusted.');
            }

            $this->integrityVerifier->verify($plugin);
        } catch (PluginTrustFailure $exception) {
            report($exception);
            abort(404, 'This plugin release is not trusted.');
        }

        return $next($request);
    }
}
