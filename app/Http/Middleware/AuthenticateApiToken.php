<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $plainTextToken = $request->bearerToken();
        if ($plainTextToken === null && $mode === 'optional') {
            return $next($request);
        }
        if (! is_string($plainTextToken) || strlen($plainTextToken) < 40 || strlen($plainTextToken) > 128) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token || ! $token->user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Mobile tokens are valid only for the supported app roles and must
        // remain bound to an approved, account-owned trusted device.
        if (! in_array($token->user->role, ['student', 'teacher'], true)) {
            $token->forceFill(['revoked_at' => now()])->save();

            return response()->json(['message' => 'This account role cannot use the mobile applications. Sign in again.'], 401);
        }

        // Student tokens must always be bound to an approved device. Teacher
        // tokens are password-authenticated and intentionally have no device row.
        $deviceApproved = $token->user->isTeacher() && $token->student_device_id === null;

        if (! $deviceApproved && $token->student_device_id !== null) {
            $deviceApproved = \App\Models\StudentDevice::query()
                ->whereKey($token->student_device_id)
                ->where('user_id', $token->user_id)
                ->where('status', 'approved')
                ->exists();
        }

        if (! $deviceApproved) {
            $token->forceFill(['revoked_at' => now()])->save();

            return response()->json(['message' => 'This mobile device is not approved. Sign in again after device verification.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
