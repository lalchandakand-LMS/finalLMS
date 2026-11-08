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
        if (app()->environment('testing')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $sessionHash = hash('sha256', $request->session()->getId());
        $deviceSession = DeviceSession::query()
            ->where('user_id', $user->id)
            ->where('session_hash', $sessionHash)
            ->first();

        if (! $deviceSession || $deviceSession->revoked_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('This login session has expired or was signed out by an administrator. Please verify your device and sign in again.'));
        }

        if (! $deviceSession->last_seen_at || $deviceSession->last_seen_at->lt(now()->subMinute())) {
            $deviceSession->forceFill(['last_seen_at' => now(), 'ip_address' => $request->ip()])->save();
        }

        return $next($request);
    }
}
