<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRouteModuleEnabled
{
    public function __construct(private readonly ModuleRegistry $modules)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = $route?->getName();
        $routeUri = $route?->uri();

        foreach (config('module_routes', []) as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (is_string($routeName) && $routeName !== '' && Str::is($pattern, $routeName)) {
                    abort_unless($this->modules->isEnabled($module), 404, 'This LMS module is currently disabled.');
                    break;
                }
            }
        }

        foreach (config('module_route_uris', []) as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (is_string($routeUri) && $routeUri !== '' && Str::is($pattern, $routeUri)) {
                    abort_unless($this->modules->isEnabled($module), 404, 'This LMS module is currently disabled.');
                }
            }
        }

        return $next($request);
    }
}
