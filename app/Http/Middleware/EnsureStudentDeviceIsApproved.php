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

        if (! $user || $user->isAdmin() || ! $deviceId) {
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
