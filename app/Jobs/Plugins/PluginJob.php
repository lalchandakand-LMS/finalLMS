<?php

namespace App\Jobs\Plugins;

use App\Http\Middleware\EnsurePluginJobActive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Base contract for plugin-owned queued work.
 *
 * Every job must identify its immutable plugin key and a manifest-declared
 * permission that an Admin has explicitly granted.
 */
abstract class PluginJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $pluginKey,
        public readonly string $requiredPermission,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]{1,158}$/D', $pluginKey) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{1,158}$/D', $requiredPermission) !== 1) {
            throw new InvalidArgumentException('Plugin jobs require a valid plugin key and permission identifier.');
        }
    }

    /**
     * Laravel invokes this middleware before the queued handler. If the plugin
     * was deactivated, failed, untrusted, or lost its permission grant after
     * dispatch, the stale job is acknowledged without executing its handler.
     *
     * @return list<EnsurePluginJobActive>
     */
    public function middleware(): array
    {
        return [new EnsurePluginJobActive($this->pluginKey, $this->requiredPermission)];
    }
}
