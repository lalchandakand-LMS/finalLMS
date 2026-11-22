<?php

namespace App\Http\Middleware;

use App\Models\StudentProfile;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            abort(403, 'You are not authorized to access this area.');
        }

        if ($request->user()->role === 'student' && in_array('student', $roles, true)) {
            $suspended = \App\Models\StudentProfile::query()
                ->where('user_id', $request->user()->id)
                ->where('lifecycle_status', 'suspended')
                ->exists();

            if ($suspended) {
                abort(403, 'This student account is suspended. Please contact the administration.');
            }
        }

        return $next($request);
    }
}
