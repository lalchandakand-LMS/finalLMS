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
        $shouldTrack = $request->isMethod('GET')
            && !$request->expectsJson()
            && !$request->is('admin/*', 'dashboard', 'login', 'register', 'install*', 'up', 'student-device/*', 'teacher/apply*', 'courses/*/lessons/*')
            && !str_contains((string) $request->header('accept'), 'application/json');

        if ($shouldTrack) {
            $visitorKey = $request->cookie('aec_visitor');
            if (!is_string($visitorKey) || !preg_match('/^[A-Za-z0-9]{32,128}$/', $visitorKey)) {
                $visitorKey = $request->hasSession() ? $request->session()->getId() : bin2hex(random_bytes(32));
            }
            $request->attributes->set('analytics_visitor_key', $visitorKey);
        }

        $response = $next($request);

        if ($shouldTrack && $response->getStatusCode() < 400) {
            PageView::record($request, 'site');
            if (!$request->cookie('aec_visitor')) {
                $response->headers->setCookie(cookie(
                    'aec_visitor',
                    (string) $request->attributes->get('analytics_visitor_key'),
                    525600,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'lax'
                ));
            }
        }

        return $response;
    }
}
