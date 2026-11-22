<?php

namespace App\Services\Plugins;

use App\Contracts\Payments\PaymentGateway;
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

        $this->router->middleware($middleware)->prefix($prefix)->as($namePrefix)->group($routes);
        // Refresh named-route lookups after plugin groups apply their route-name prefixes.
        $this->router->getRoutes()->refreshNameLookups();
    }
    /**
     * Register mobile API routes for the roles supported by the LMS device-token
     * authentication boundary. Guardian, branch and admin APIs must not bypass
     * their own authentication model through this mobile-token route helper.
     */
    public function apiRoutesForPortal(
        string $portal,
        string $prefix,
        string $namePrefix,
        Closure $routes,
        string $permission,
    ): void {
        $roles = [
            'student' => 'student',
            'teacher' => 'teacher',
        ];

        if (! isset($roles[$portal])
            || ! in_array($portal, $this->plugin->manifest['portals'] ?? [], true)) {
            throw new InvalidArgumentException('Plugin API routes are supported only for declared student and teacher mobile portals.');
        }

        [$vendor, $slug] = explode('.', (string) $this->plugin->plugin_key, 2);
        $expectedPrefix = 'api/v1/plugins/'.$vendor.'/'.str_replace('.', '-', $slug);
        if (! hash_equals($expectedPrefix, $prefix)
            || preg_match('/^api\/v1\/plugins\/[a-z0-9][a-z0-9\/-]{0,99}$/D', $prefix) !== 1
            || str_contains($prefix, '..')
            || preg_match('/^[a-z][a-z0-9_.-]{0,119}\.$/D', $namePrefix) !== 1) {
            throw new InvalidArgumentException('The plugin API route prefix must use its canonical vendor/plugin namespace and a valid route-name prefix.');
        }

        $declaredPermissions = $this->plugin->manifest['permissions'] ?? [];
        if (! is_array($declaredPermissions) || ! in_array($permission, $declaredPermissions, true)) {
            throw new InvalidArgumentException('Every plugin API route group must require a permission declared in the signed manifest.');
        }

        Route::middleware([
            'api',
            'plugin.active:'.$this->plugin->plugin_key,
            'api.token',
            'role:'.$roles[$portal],
            'plugin.permission:'.$this->plugin->plugin_key.','.$permission,
            'throttle:60,1',
        ])->prefix($prefix)->name($namePrefix)->group($routes);
        // Dynamic API routes need the same named-route lookup refresh as web plugin routes.
        $this->router->getRoutes()->refreshNameLookups();
    }

    public function registerHook(string $hook, string $permission, callable $callback, int $priority = 10): void
    {
        if (! $this->hasPermission($permission)) {
            throw new InvalidArgumentException('Plugin hooks must require a permission declared in the signed manifest.');
        }

        $this->hooks()->registerHook((string) $this->plugin->plugin_key, $hook, $permission, $callback, $priority);
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
        if ($portal === 'public') {
            if ($permission !== null) {
                throw new InvalidArgumentException('Public plugin navigation cannot use authenticated permission grants.');
            }
        } elseif (! is_string($permission)
            || ! in_array($permission, $this->plugin->manifest['permissions'] ?? [], true)) {
            throw new InvalidArgumentException('Authenticated plugin navigation must use a permission declared in the signed manifest.');
        }

        $this->hooks()->registerPortalNavigation(
            (string) $this->plugin->plugin_key,
            $portal,
            $label,
            $routeName,
            $options,
        );
    }

    /**
     * Register a payment adapter from this active, signature-verified plugin.
     * The signed manifest must declare the payments.gateway capability.
     *
     * @param class-string<PaymentGateway> $driver
     */
    public function registerPaymentGateway(string $key, string $driver): void
    {
        if (! $this->hasCapability('payments.gateway')) {
            throw new InvalidArgumentException('The plugin manifest must declare the payments.gateway capability.');
        }

        if (! is_subclass_of($driver, PaymentGateway::class)) {
            throw new InvalidArgumentException('Payment gateway plugins must implement the payment gateway contract.');
        }

        $this->app->make(PaymentGatewayManager::class)->registerPluginGateway(
            (string) $this->plugin->plugin_key,
            $key,
            $driver,
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
