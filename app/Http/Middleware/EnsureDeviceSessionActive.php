<?php

namespace App\Http\Middleware;

use App\Models\DeviceSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceSessionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $sessionHash = $request->session()->get('device_session_hash');
        if (! is_string($sessionHash) || ! preg_match('/^[a-f0-9]{64}$/', $sessionHash)) {
            $sessionHash = hash('sha256', $request->session()->getId());
        }

        $session = DeviceSession::query()
            ->where('user_id', $user->id)
            ->where('session_hash', $sessionHash)
            ->first();

        // Sessions created before this feature remain valid until their next sign-in.
        if (! $session) {
            return $next($request);
        }

        if ($session->revoked_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('This login session was signed out by an administrator. Please sign in again.'));
        }

        if (! $session->last_seen_at || $session->last_seen_at->lt(now()->subMinute())) {
            $session->forceFill(['last_seen_at' => now(), 'ip_address' => $request->ip()])->save();
        }

        return $next($request);
    }
}
