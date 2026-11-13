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

        if ($token->user->isStudent()) {
            $deviceApproved = $token->student_device_id !== null
                && \App\Models\StudentDevice::query()
                    ->whereKey($token->student_device_id)
                    ->where('user_id', $token->user_id)
                    ->where('status', 'approved')
                    ->exists();

            if (! $deviceApproved) {
                $token->forceFill(['revoked_at' => now()])->save();

                return response()->json(['message' => 'This student device is not approved. Sign in again after device verification.'], 401);
            }
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
