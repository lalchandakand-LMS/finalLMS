<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleRegistry $modules)
    {
    }

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->isEnabled($module), 404, 'This LMS module is currently disabled.');

        return $next($request);
    }
}
