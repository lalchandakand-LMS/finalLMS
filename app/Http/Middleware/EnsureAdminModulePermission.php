<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminModulePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You are not authorized to access this area.');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($permissions === []) {
            abort(403, 'No module permission was specified.');
        }

        foreach ($permissions as $permission) {
            if (! $user->hasAdminPermission($permission)) {
                abort(403, 'You do not have permission to access this module.');
            }
        }

        return $next($request);
    }
}
