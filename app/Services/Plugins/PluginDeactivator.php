<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use RuntimeException;

final class PluginDeactivator
{
    public function __construct(private readonly PluginLifecycle $lifecycle)
    {
    }

    public function deactivate(PluginInstallation $plugin): PluginInstallation
    {
        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());
        if ($plugin->status !== 'active') {
            throw new RuntimeException('Only an active plugin can be deactivated.');
        }

        $dependents = PluginInstallation::query()
            ->whereIn('status', ['active'])
            ->where('id', '!=', $plugin->getKey())
            ->get(['plugin_key', 'manifest']);

        foreach ($dependents as $dependent) {
            $dependencies = is_array($dependent->manifest) ? ($dependent->manifest['dependencies'] ?? []) : [];
            if (is_array($dependencies) && in_array($plugin->plugin_key, $dependencies, true)) {
                throw new RuntimeException('Deactivate dependent plugin first: '.$dependent->plugin_key.'.');
            }
        }

        // Third-party PHP entrypoints are deliberately never loaded by this runtime.
        // Changing the state therefore closes the only currently supported plugin entry point.
        return $this->lifecycle->transition((string) $plugin->plugin_key, 'inactive');
    }
}
