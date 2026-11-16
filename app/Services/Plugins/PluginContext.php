<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class PluginContext
{
    private const PORTAL_ROLES = [
        'admin' => 'admin',
        'branch' => 'branch_manager',
        'teacher' => 'teacher',
        'student' => 'student',
        'guardian' => 'guardian',
        'support_staff' => 'accountant',
        'public' => null,
    ];

    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly PluginInstallation $plugin,
    ) {}

    public function plugin(): PluginInstallation
    {
        return $this->plugin;
    }

    public function bind(string $abstract, mixed $concrete): void
    {
        $this->app->bind($abstract, $concrete);
    }

    public function singleton(string $abstract, mixed $concrete): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->app->instance($abstract, $instance);
    }

    public function routesForPortal(string $portal, string $prefix, string $namePrefix, Closure $routes, ?string $permission = null): void
    {
        if (! array_key_exists($portal, self::PORTAL_ROLES)
            || ! in_array($portal, $this->plugin->manifest['portals'] ?? [], true)) {
            throw new InvalidArgumentException('The plugin did not declare this portal in its signed manifest.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9\/-]{0,99}$/D', $prefix) !== 1
            || str_contains($prefix, '..')
            || preg_match('/^[a-z][a-z0-9_.-]{0,119}\.$/D', $namePrefix) !== 1) {
            throw new InvalidArgumentException('The plugin route prefix or route-name prefix is invalid.');
        }

        $middleware = ['web', 'plugin.active:'.$this->plugin->plugin_key];
        $role = self::PORTAL_ROLES[$portal];
        if ($role !== null) {
            $declaredPermissions = $this->plugin->manifest['permissions'] ?? [];
            if ($declaredPermissions !== []) {
                if (! is_string($permission) || ! in_array($permission, $declaredPermissions, true)) {
                    throw new InvalidArgumentException('Authenticated plugin routes must declare one of the permissions in the signed manifest.');
                }
                $middleware[] = 'plugin.permission:'.$this->plugin->plugin_key.','.$permission;
            } elseif ($permission !== null) {
                throw new InvalidArgumentException('A plugin route cannot request a permission absent from its signed manifest.');
            }
            $middleware[] = 'auth';
            $middleware[] = 'verified';
            $middleware[] = 'role:'.$role;
        } elseif ($permission !== null) {
            throw new InvalidArgumentException('Public plugin routes cannot use authenticated permission grants.');
        }

        Route::middleware($middleware)->prefix($prefix)->name($namePrefix)->group($routes);
    }
}
