<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        if (! in_array($user->role, ['student', 'teacher'], true)) {
            return response()->json(['message' => 'This account cannot use the mobile applications.'], 403);
        }

        $plainTextToken = Str::random(80);
        $token = $user->apiTokens()->create([
            'name' => $validated['device_name'],
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => $token->expires_at->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');
        abort_unless($token instanceof ApiToken, 401);
        $token->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => 'Device session revoked.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->apiTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'All mobile sessions revoked.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
        ];
    }
}
