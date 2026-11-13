<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\StudentDevice;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request, SmsGateway $sms): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['required', 'string', 'min:2', 'max:100'],
            'device_id' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        if (! in_array($user->role, ['student', 'teacher'], true)) {
            return response()->json(['message' => 'This account cannot use the mobile applications.'], 403);
        }

        if ($user->isTeacher()) {
            return $this->issueToken($user, null, $validated['device_name']);
        }

        if (! $user->phone_number) {
            return response()->json(['message' => 'A verified mobile number is required. Contact the academy administrator.'], 422);
        }

        $deviceHash = hash('sha256', $validated['device_id']);
        $device = StudentDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->first();

        if ($device?->status === 'approved') {
            DB::transaction(function () use ($user, $device): void {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                StudentDevice::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $device->id)
                    ->where('status', 'approved')
                    ->update(['status' => 'revoked', 'updated_at' => now()]);
                ApiToken::query()
                    ->where('user_id', $user->id)
                    ->where(function ($query) use ($device): void {
                        $query->whereNull('student_device_id')
                            ->orWhere('student_device_id', '!=', $device->id);
                    })
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
            });

            $device->forceFill(['last_used_at' => now()])->save();

            return $this->issueToken($user, $device, $validated['device_name']);
        }

        if (! $sms->isConfigured()) {
            return response()->json(['message' => 'Mobile device verification is unavailable. Contact the academy administrator.'], 503);
        }

        $otp = (string) random_int(100000, 999999);
        $challenge = bin2hex(random_bytes(32));
        $device = DB::transaction(function () use ($user, $deviceHash, $validated, $request, $otp, $challenge): StudentDevice {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $device = StudentDevice::query()
                ->where('user_id', $user->id)
                ->where('device_hash', $deviceHash)
                ->lockForUpdate()
                ->first();

            if (! $device) {
                $device = new StudentDevice([
                    'user_id' => $user->id,
                    'device_hash' => $deviceHash,
                ]);
            }

            $device->forceFill([
                'device_name' => $validated['device_name'],
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'ip_address' => $request->ip(),
                'status' => 'pending_phone_verification',
                'phone_otp_hash' => Hash::make($otp),
                'phone_otp_expires_at' => now()->addMinutes(10),
                'phone_verified_at' => null,
                'rejected_at' => null,
                'api_challenge_hash' => hash('sha256', $challenge),
                'api_challenge_expires_at' => now()->addDay(),
            ])->save();

            return $device;
        });

        try {
            $sms->send($user->phone_number, "Alom's English Center: Your mobile app login code is {$otp}. It expires in 10 minutes.");
        } catch (Throwable $exception) {
            $device->forceFill([
                'status' => 'sms_failed',
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
                'api_challenge_hash' => null,
                'api_challenge_expires_at' => null,
            ])->save();

            report($exception);

            return response()->json(['message' => 'The verification SMS could not be sent. Please contact the academy administrator.'], 503);
        }

        return response()->json([
            'status' => 'phone_verification_required',
            'challenge_token' => $challenge,
            'expires_in' => 600,
            'message' => 'A verification code has been sent to the mobile number registered with the academy.',
        ], 202);
    }

    public function verifyDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'code' => ['required', 'digits:6'],
        ]);

        $challengeHash = hash('sha256', $validated['challenge_token']);
        $device = StudentDevice::query()
            ->where('api_challenge_hash', $challengeHash)
            ->with('user')
            ->first();

        if (! $device || ! $device->user || ! $device->api_challenge_expires_at?->isFuture()) {
            return response()->json(['message' => 'The verification request is invalid or expired. Sign in again.'], 410);
        }

        $key = 'mobile-device-otp:'.substr($challengeHash, 0, 32);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many incorrect codes. Start a new login after the cooldown.'], 429);
        }

        if ($device->status !== 'pending_phone_verification'
            || ! $device->phone_otp_hash
            || ! $device->phone_otp_expires_at?->isFuture()
            || ! Hash::check($validated['code'], $device->phone_otp_hash)) {
            RateLimiter::hit($key, 600);

            return response()->json(['message' => 'The verification code is invalid or expired.'], 422);
        }

        $result = DB::transaction(function () use ($device): string {
            User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $locked = StudentDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending_phone_verification'
                || ! $locked->phone_otp_hash
                || ! $locked->phone_otp_expires_at?->isFuture()
                || ! $locked->api_challenge_expires_at?->isFuture()) {
                return 'expired';
            }

            $hasPriorDevice = StudentDevice::query()
                ->where('user_id', $locked->user_id)
                ->where('id', '!=', $locked->id)
                ->exists();
            $requiresAdminApproval = $hasPriorDevice || $locked->approved_at !== null;

            $locked->forceFill([
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
                'phone_verified_at' => now(),
                'status' => $requiresAdminApproval ? 'pending_admin_approval' : 'approved',
                'approved_at' => $requiresAdminApproval ? $locked->approved_at : now(),
                'last_used_at' => $requiresAdminApproval ? null : now(),
            ])->save();

            if (! $locked->user->phone_verified_at) {
                $locked->user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $requiresAdminApproval ? 'approval_required' : 'approved';
        });

        RateLimiter::clear($key);

        if ($result === 'expired') {
            return response()->json(['message' => 'The verification request expired. Start a new login.'], 410);
        }

        if ($result === 'approval_required') {
            return response()->json([
                'status' => 'pending_admin_approval',
                'message' => 'Mobile number verified. The academy administrator must approve this device before app access is enabled.',
                'status_check_required' => true,
            ], 202);
        }

        return $this->issueToken($device->user, $device, $device->device_name ?? 'Student mobile app', $challengeHash);
    }

    public function deviceStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $challengeHash = hash('sha256', $validated['challenge_token']);
        $device = StudentDevice::query()
            ->where('api_challenge_hash', $challengeHash)
            ->with('user')
            ->first();

        if (! $device || ! $device->user || ! $device->api_challenge_expires_at?->isFuture()) {
            return response()->json(['message' => 'The device approval request is invalid or expired. Sign in again.'], 410);
        }

        if ($device->status === 'pending_admin_approval') {
            return response()->json([
                'status' => 'pending_admin_approval',
                'message' => 'Your device is waiting for academy administrator approval.',
            ], 202);
        }

        if ($device->status === 'rejected' || $device->status === 'revoked') {
            return response()->json(['message' => 'This device request was not approved. Contact the academy.'], 403);
        }

        if ($device->status !== 'approved' || ! $device->phone_verified_at) {
            return response()->json(['message' => 'The device is not ready for sign-in. Start a new login.'], 409);
        }

        return $this->issueToken($device->user, $device, $device->device_name ?? 'Student mobile app', $challengeHash);
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

    private function issueToken(User $user, ?StudentDevice $device, string $deviceName, ?string $challengeHash = null): JsonResponse
    {
        $plainTextToken = Str::random(80);
        $token = DB::transaction(function () use ($user, $device, $deviceName, $challengeHash, $plainTextToken): ApiToken {
            if ($device) {
                $lockedDevice = StudentDevice::query()
                    ->whereKey($device->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($challengeHash !== null && ! hash_equals((string) $lockedDevice->api_challenge_hash, $challengeHash)) {
                    abort(410, 'The device approval request has already been used or expired.');
                }

                $lockedDevice->forceFill([
                    'api_challenge_hash' => null,
                    'api_challenge_expires_at' => null,
                    'last_used_at' => now(),
                ])->save();
            }

            return $user->apiTokens()->create([
                'student_device_id' => $device?->id,
                'name' => $deviceName,
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now()->addDays(30),
            ]);
        });

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => $token->expires_at->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
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
