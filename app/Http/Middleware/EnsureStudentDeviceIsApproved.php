<?php

namespace App\Http\Middleware;

use App\Models\StudentDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentDeviceIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $deviceId = $request->session()->get('student_device_id');

        if (! $user) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            $enabledAt = $user->admin_totp_enabled_at?->timestamp;
            $verifiedAt = $request->session()->get('admin_mfa_verified_at');
            if (! $user->admin_totp_secret
                || ! $enabledAt
                || ! is_numeric($verifiedAt)
                || (int) $verifiedAt < $enabledAt) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('warning', 'Administrator two-factor verification is required. Please sign in again.');
            }

            return $next($request);
        }

        if ($request->session()->has('admin_mfa_verified_at')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('warning', 'Your account role changed. Please sign in again using the verification method for your current role.');
        }

        // Legacy non-admin sessions remain valid until sign-out/expiry. New
        // non-admin logins always attach an approved trusted-device ID.
        if (! $deviceId) {
            return $next($request);
        }

        $isApproved = StudentDevice::query()
            ->whereKey($deviceId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();

        if (! $isApproved) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('warning', 'This device no longer has access. Please sign in using an approved device.');
        }

        return $next($request);
    }
}
