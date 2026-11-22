<?php

namespace App\Http\Controllers;

use App\Models\DeviceSession;
use App\Models\StudentDevice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\SmsGateway;
use App\Notifications\AdminMfaBootstrapCodeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class StudentDeviceAccessController extends Controller
{
    public function showVerify(Request $request): View|RedirectResponse
    {
        $device = StudentDevice::query()
            ->whereKey($request->session()->get('student_device_request_id'))
            ->whereIn('status', ['pending_phone_verification', 'pending_admin_approval'])
            ->first();

        if (! $device) {
            return redirect()->route('login')->with('error', 'No active device verification request was found. Please sign in again.');
        }

        return view('auth.device-verification', compact('device'));
    }

    public function verifyPhone(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $device = StudentDevice::query()
            ->whereKey($request->session()->get('student_device_request_id'))
            ->where('status', 'pending_phone_verification')
            ->first();

        if (! $device || ! $device->phone_otp_hash || ! $device->phone_otp_expires_at?->isFuture()) {
            return redirect()->route('login')->with('error', 'The verification request expired. Please sign in again.');
        }

        $key = 'student-device-otp:'.$device->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many incorrect codes. Please sign in again later.');
        }

        if (! Hash::check($validated['code'], $device->phone_otp_hash)) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['code' => 'The verification code is invalid or expired.']);
        }

        $user = $device->user;
        if (! $user || $user->isAdmin()) {
            return redirect()->route('login')->with('error', 'Administrator accounts must use authenticator-based MFA.');
        }

        $result = DB::transaction(function () use ($device, $user, $validated): string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedDevice = StudentDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($lockedDevice->status !== 'pending_phone_verification'
                || ! $lockedDevice->phone_otp_hash
                || ! $lockedDevice->phone_otp_expires_at?->isFuture()) {
                return 'expired';
            }

            // Re-check the submitted code against the locked row so a concurrent
            // resend cannot let a stale OTP consume the replacement challenge.
            if (! Hash::check($validated['code'], $lockedDevice->phone_otp_hash)) {
                return 'invalid';
            }

            $hasPriorDevice = StudentDevice::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $lockedDevice->id)
                ->exists();
            $wasPreviouslyTrusted = $lockedDevice->approved_at !== null;
            $requiresAdminApproval = $hasPriorDevice || $wasPreviouslyTrusted;

            $lockedDevice->forceFill([
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
                'phone_verified_at' => now(),
                'status' => $requiresAdminApproval ? 'pending_admin_approval' : 'approved',
                'approved_at' => $requiresAdminApproval ? $lockedDevice->approved_at : now(),
                'last_used_at' => $requiresAdminApproval ? null : now(),
            ])->save();

            if (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $requiresAdminApproval ? 'approval_required' : 'approved';
        });

        if ($result === 'invalid') {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'The verification code is invalid or expired.']);
        }

        RateLimiter::clear($key);

        if ($result === 'expired') {
            $request->session()->forget('student_device_request_id');
            return redirect()->route('login')->with('error', 'The verification request expired. Please sign in again.');
        }

        if ($result === 'approval_required') {
            $request->session()->forget('student_device_request_id');
            return redirect()->route('login')->with('warning', 'Mobile number verified. This device needs administrator approval before sign-in.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        DeviceSession::register($request, $user, $device);
        $request->session()->put('student_device_id', $device->id);
        $request->session()->forget('student_device_request_id');

        return redirect()->intended('/dashboard')->with('success', 'Mobile number verified and this device is now trusted.');
    }

    public function adminIndex(): View
    {
        $requests = StudentDevice::query()
            ->with('user')
            ->where('status', 'pending_admin_approval')
            ->latest()
            ->paginate(25);

        $devices = StudentDevice::query()
            ->with(['user', 'approver'])
            ->whereIn('status', ['approved', 'rejected', 'revoked'])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return view('admin.student-devices.index', compact('requests', 'devices'));
    }

    public function approve(StudentDevice $device, Request $request): RedirectResponse
    {
        $approved = DB::transaction(function () use ($device, $request): bool {
            User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $devices = StudentDevice::query()
                ->where('user_id', $device->user_id)
                ->lockForUpdate()
                ->get();
            $target = $devices->firstWhere('id', $device->id);

            if (! $target || $target->status !== 'pending_admin_approval' || ! $target->phone_verified_at) {
                return false;
            }

            StudentDevice::query()
                ->where('user_id', $device->user_id)
                ->where('id', '!=', $device->id)
                ->where('status', 'approved')
                ->update(['status' => 'revoked', 'updated_at' => now()]);

            DB::table('api_tokens')
                ->where('user_id', $device->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            DeviceSession::query()
                ->where('user_id', $device->user_id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'Another trusted device was approved by administrator',
                    'updated_at' => now(),
                ]);

            $target->forceFill([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'last_used_at' => null,
            ])->save();

            return true;
        });

        if (! $approved) {
            return back()->with('error', 'Only phone-verified pending devices can be approved.');
        }

        return back()->with('success', 'Device approved. Previous device access has been revoked.');
    }

    public function reject(StudentDevice $device): RedirectResponse
    {
        $rejected = DB::transaction(function () use ($device): bool {
            User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $target = StudentDevice::query()->whereKey($device->id)->lockForUpdate()->first();

            if (! $target || $target->status !== 'pending_admin_approval' || ! $target->phone_verified_at) {
                return false;
            }

            $target->forceFill([
                'status' => 'rejected',
                'rejected_at' => now(),
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
                'api_challenge_hash' => null,
                'api_challenge_expires_at' => null,
            ])->save();

            return true;
        });

        if (! $rejected) {
            return back()->with('error', 'This phone-verified device is not awaiting approval.');
        }

        return back()->with('success', 'Additional device request rejected.');
    }

    public function revoke(StudentDevice $device): RedirectResponse
    {
        $revoked = DB::transaction(function () use ($device): bool {
            User::query()->whereKey($device->user_id)->lockForUpdate()->firstOrFail();
            $target = StudentDevice::query()->whereKey($device->id)->lockForUpdate()->first();

            if (! $target || $target->status !== 'approved') {
                return false;
            }

            $target->forceFill(['status' => 'revoked'])->save();
            DB::table('api_tokens')
                ->where('student_device_id', $target->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            DeviceSession::query()->where('student_device_id', $target->id)->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Trusted device revoked by administrator',
                'updated_at' => now(),
            ]);

            return true;
        });

        if (! $revoked) {
            return back()->with('error', 'Only an approved device can be revoked.');
        }

        return back()->with('success', 'Device access revoked. Its web and mobile sessions are now invalid.');
    }

    public function beginLogin(Request $request, SmsGateway $sms): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        if ($user->isStudent() && StudentProfile::query()->where('user_id', $user->id)->whereIn('lifecycle_status', ['suspended', 'graduated'])->exists()) {
            return back()->withErrors(['email' => 'This student account is inactive. Please contact the administration.'])->onlyInput('email');
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Never carry a pending MFA or device-verification challenge from a
        // previous identity into a new successful password-authentication flow.
        $request->session()->forget([
            'pending_admin_mfa_user_id',
            'pending_admin_mfa_started_at',
            'pending_admin_mfa_password_hash',
            'admin_mfa_setup_secret',
            'admin_mfa_bootstrap_code_hash',
            'admin_mfa_bootstrap_expires_at',
            'admin_mfa_bootstrap_email_hash',
            'admin_mfa_bootstrap_verified_at',
            'admin_mfa_verified_at',
            'student_device_request_id',
            'student_device_id',
            'device_session_hash',
        ]);

        if ($user->isAdmin()) {

            $hasEnabledAt = $user->admin_totp_enabled_at !== null;
            $hasSecret = is_string($user->admin_totp_secret) && $user->admin_totp_secret !== '';
            if ($hasEnabledAt !== $hasSecret) {
                return back()->withErrors(['email' => 'Administrator MFA needs secure account recovery. Contact the system owner.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            $request->session()->put('pending_admin_mfa_user_id', $user->id);
            $request->session()->put('pending_admin_mfa_started_at', now()->timestamp);
            $request->session()->put('pending_admin_mfa_password_hash', $user->password);

            if ($hasEnabledAt && $hasSecret) {
                return redirect()->route('admin.mfa.challenge');
            }

            if (! $user->email_verified_at) {
                $request->session()->forget([
                    'pending_admin_mfa_user_id',
                    'pending_admin_mfa_started_at',
                    'pending_admin_mfa_password_hash',
                    'admin_mfa_setup_secret',
                ]);

                return back()->withErrors(['email' => 'Verify the administrator email address before enabling authenticator MFA.'])->onlyInput('email');
            }

            $sendKey = 'admin-mfa-bootstrap-send:'.$user->id;
            if (RateLimiter::tooManyAttempts($sendKey, 3)) {
                $request->session()->forget([
                    'pending_admin_mfa_user_id',
                    'pending_admin_mfa_started_at',
                    'pending_admin_mfa_password_hash',
                ]);

                return back()->withErrors(['email' => 'Administrator verification email is temporarily rate limited. Try again later.'])->onlyInput('email');
            }
            RateLimiter::hit($sendKey, 600);

            $bootstrapCode = (string) random_int(100000, 999999);
            $request->session()->put('admin_mfa_bootstrap_code_hash', Hash::make($bootstrapCode));
            $request->session()->put('admin_mfa_bootstrap_email_hash', hash('sha256', strtolower($user->email)));
            $request->session()->put('admin_mfa_bootstrap_expires_at', now()->addMinutes(10)->timestamp);

            try {
                Notification::route('mail', $user->email)->notify(new AdminMfaBootstrapCodeNotification($bootstrapCode));
            } catch (Throwable $exception) {
                report($exception);
                $request->session()->forget([
                    'pending_admin_mfa_user_id',
                    'pending_admin_mfa_started_at',
                    'pending_admin_mfa_password_hash',
                    'admin_mfa_bootstrap_code_hash',
                    'admin_mfa_bootstrap_expires_at',
                    'admin_mfa_bootstrap_email_hash',
                    'admin_mfa_setup_secret',
                ]);

                return back()->withErrors(['email' => 'The administrator verification email could not be sent. Contact the system owner.'])->onlyInput('email');
            }

            return redirect()->route('admin.mfa.bootstrap');
        }

        if (! $user->phone_number) {
            return back()->withErrors(['email' => 'Add a mobile number to your account through the academy administrator before signing in.'])->onlyInput('email');
        }

        $deviceToken = $request->cookie('lms_student_device');
        if (! is_string($deviceToken) || ! preg_match('/^[a-f0-9]{64}$/', $deviceToken)) {
            $deviceToken = bin2hex(random_bytes(32));
        }
        $deviceHash = hash('sha256', $deviceToken);

        $device = StudentDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->first();

        if ($device?->status === 'approved' && $device->phone_verified_at) {
            $hasAnotherApprovedDevice = StudentDevice::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $device->id)
                ->where('status', 'approved')
                ->exists();

            if ($hasAnotherApprovedDevice) {
                DB::transaction(function () use ($user, $device): void {
                    User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    StudentDevice::query()
                        ->where('user_id', $user->id)
                        ->where('id', '!=', $device->id)
                        ->where('status', 'approved')
                        ->update(['status' => 'revoked', 'updated_at' => now()]);

                    DeviceSession::query()
                        ->where('user_id', $user->id)
                        ->whereNull('revoked_at')
                        ->where(function ($query) use ($device): void {
                            $query->whereNull('student_device_id')
                                ->orWhere('student_device_id', '!=', $device->id);
                        })
                        ->update([
                            'revoked_at' => now(),
                            'revoke_reason' => 'Another trusted device was used to sign in',
                            'updated_at' => now(),
                        ]);
                });
            }

            $device->forceFill(['last_used_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();
            DeviceSession::register($request, $user, $device);
            $request->session()->put('student_device_id', $device->id);

            return redirect()->intended('/dashboard')
                ->withCookie(cookie('lms_student_device', $deviceToken, 60 * 24 * 365, '/', null, true, true, false, 'lax'));
        }

        if (! $sms->isConfigured()) {
            return back()->withErrors(['email' => 'Device verification needs the academy SMS service. Please contact the administrator.'])->onlyInput('email');
        }

        $otp = (string) random_int(100000, 999999);
        $device = StudentDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_hash' => $deviceHash],
            [
                'device_name' => $this->deviceName($request->userAgent()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'ip_address' => $request->ip(),
                'status' => 'pending_phone_verification',
                'phone_otp_hash' => Hash::make($otp),
                'phone_otp_expires_at' => now()->addMinutes(10),
                'phone_verified_at' => null,
                'approved_by' => $device?->approved_by,
                'approved_at' => $device?->approved_at,
                'rejected_at' => null,
            ]
        );

        try {
            $sms->send($user->phone_number, "Alom's English Center: Your new-device login verification code is {$otp}. It expires in 10 minutes.");
        } catch (Throwable $exception) {
            $device->forceFill([
                'status' => 'sms_failed',
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
            ])->save();

            return back()->withErrors(['email' => 'The verification SMS could not be sent. Please contact the academy administrator.'])->onlyInput('email');
        }

        $request->session()->put('student_device_request_id', $device->id);

        return redirect()->route('student-device.verify')
            ->withCookie(cookie('lms_student_device', $deviceToken, 60 * 24 * 365, '/', null, true, true, false, 'lax'));
    }

    private function deviceName(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);
        $browser = str_contains($agent, 'edg/') ? 'Edge' : (str_contains($agent, 'chrome/') ? 'Chrome' : (str_contains($agent, 'firefox/') ? 'Firefox' : (str_contains($agent, 'safari/') ? 'Safari' : 'Unknown browser')));
        $platform = str_contains($agent, 'android') ? 'Android' : (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') ? 'iOS' : (str_contains($agent, 'windows') ? 'Windows' : (str_contains($agent, 'mac os') ? 'macOS' : (str_contains($agent, 'linux') ? 'Linux' : 'Unknown device'))));

        return $platform.' · '.$browser;
    }
}
