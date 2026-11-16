<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use App\Models\PluginVersionHistory;
use App\Models\User;
use Throwable;

final class PluginVersionHistoryRecorder
{
    public function recordSnapshot(PluginInstallation $plugin, ?User $actor, array $snapshot): void
    {
        PluginVersionHistory::query()->create([
            'plugin_key' => $plugin->plugin_key,
            'version' => $plugin->version,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'action' => 'version_snapshot',
            'status' => $plugin->status,
            'actor_id' => $actor?->id ?? auth()->id(),
            'details' => ['snapshot' => $snapshot],
            'created_at' => now(),
        ]);
    }

    public function record(
        PluginInstallation $plugin,
        string $action,
        ?User $actor = null,
        array $details = [],
    ): void {
        try {
            PluginVersionHistory::query()->create([
                'plugin_key' => $plugin->plugin_key,
                'version' => $plugin->version,
                'package_sha256' => $plugin->package_sha256,
                'signature_key_id' => $plugin->signature_key_id,
                'action' => $action,
                'status' => $plugin->status,
                'actor_id' => $actor?->id ?? auth()->id(),
                'details' => $details === [] ? null : $details,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
