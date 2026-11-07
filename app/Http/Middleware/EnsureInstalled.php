<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || $request->routeIs('install.*')) {
            return $next($request);
        }

        $environmentExists = is_file(base_path('.env'));
        $installed = (bool) config('app.installed', true);

        if (! $environmentExists || ! $installed) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
