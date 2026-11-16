<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->user()
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() < 400
        ) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => strtolower($request->method()),
                'description' => 'Successful '.strtoupper($request->method()).' request',
                'method' => strtoupper($request->method()),
                'route' => substr($request->route()?->getName() ?? $request->path(), 0, 190),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        }

        return $response;
    }
}
