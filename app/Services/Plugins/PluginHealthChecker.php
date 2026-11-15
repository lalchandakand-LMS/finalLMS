<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Throwable;

final class PluginHealthChecker
{
    public function __construct(private readonly PluginIntegrityVerifier $integrityVerifier)
    {
    }

    public function check(PluginInstallation $plugin): PluginInstallation
    {
        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());

        try {
            if (! in_array($plugin->status, ['installed', 'active', 'inactive'], true)
                || $plugin->signature_verified_at === null
                || ! is_string($plugin->signature_key_id)
                || ! array_key_exists($plugin->signature_key_id, (array) config('plugins.trusted_public_keys', []))) {
                throw new \RuntimeException('Plugin is not in a verifiable installed state.');
            }

            $this->integrityVerifier->verify($plugin);

            $plugin->forceFill([
                'health_status' => 'healthy',
                'health_message' => 'Signed package integrity verified. Plugin PHP entrypoints remain unloaded.',
                'last_health_check_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
            $plugin->forceFill([
                'health_status' => 'failed',
                'health_message' => 'Plugin integrity or trust verification failed. The package must remain inactive pending review.',
                'last_health_check_at' => now(),
            ])->save();
        }

        return $plugin->refresh();
    }
}
