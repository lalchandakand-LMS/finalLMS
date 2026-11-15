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
        $routeName = $request->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return $next($request);
        }

        foreach (config('module_routes', []) as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    abort_unless($this->modules->isEnabled($module), 404, 'This LMS module is currently disabled.');

                    // A route has one owning module. Do not let a broad later
                    // pattern accidentally override its ownership.
                    return $next($request);
                }
            }
        }

        return $next($request);
    }
}
