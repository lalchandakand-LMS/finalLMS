<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackWebsiteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && !$request->expectsJson() && $response->getStatusCode() < 400
            && !$request->is('admin/*', 'dashboard', 'login', 'register', 'install*', 'up', 'student-device/*', 'teacher/apply*', 'courses/*/lessons/*')
            && !str_contains((string) $request->header('accept'), 'application/json')) {
            PageView::record($request, 'site');
        }

        return $response;
    }
}
