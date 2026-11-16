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
        private readonly ?PluginHookRegistry $hookRegistry = null,
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
            if (! is_array($declaredPermissions)
                || ! is_string($permission)
                || ! in_array($permission, $declaredPermissions, true)) {
                throw new InvalidArgumentException('Every authenticated plugin route group must require a permission declared in the signed manifest.');
            }
            $permissionMiddleware = 'plugin.permission:'.$this->plugin->plugin_key.','.$permission;
            $middleware[] = 'auth';
            $middleware[] = 'verified';
            $middleware[] = 'role:'.$role;
            if (isset($permissionMiddleware)) {
                $middleware[] = $permissionMiddleware;
            }
        } elseif ($permission !== null) {
            throw new InvalidArgumentException('Public plugin routes cannot use authenticated permission grants.');
        }

        Route::middleware($middleware)->prefix($prefix)->name($namePrefix)->group($routes);
    }
    public function registerHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks()->registerHook((string) $this->plugin->plugin_key, $hook, $callback, $priority);
    }

    /**
     * @param array{icon?: string, permission?: ?string} $options
     */
    public function registerPortalNavigation(string $portal, string $label, string $routeName, array $options = []): void
    {
        if (! array_key_exists($portal, self::PORTAL_ROLES)
            || ! in_array($portal, $this->plugin->manifest['portals'] ?? [], true)) {
            throw new InvalidArgumentException('The plugin did not declare this portal in its signed manifest.');
        }

        $permission = $options['permission'] ?? null;
        if ($permission !== null
            && (! is_string($permission)
                || ! in_array($permission, $this->plugin->manifest['permissions'] ?? [], true))) {
            throw new InvalidArgumentException('Plugin navigation permissions must be declared in the signed manifest.');
        }

        $this->hooks()->registerPortalNavigation(
            (string) $this->plugin->plugin_key,
            $portal,
            $label,
            $routeName,
            $options,
        );
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->plugin->manifest['permissions'] ?? [], true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->plugin->manifest['capabilities'] ?? [], true);
    }

    private function hooks(): PluginHookRegistry
    {
        return $this->hookRegistry ?? $this->app->make(PluginHookRegistry::class);
    }

}
