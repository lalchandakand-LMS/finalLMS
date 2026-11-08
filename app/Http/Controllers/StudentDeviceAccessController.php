<?php

namespace App\Http\Controllers;

use App\Models\StudentDevice;
use App\Models\DeviceSession;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $key = 'trusted-device-otp:'.$device->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many incorrect codes. Please sign in again later.');
        }

        if (! Hash::check($validated['code'], $device->phone_otp_hash)) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['code' => 'The verification code is invalid or expired.']);
        }

        $user = $device->user;
        $hasPriorDevice = StudentDevice::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $device->id)
            ->exists();

        $device->forceFill([
            'phone_otp_hash' => null,
            'phone_otp_expires_at' => null,
            'phone_verified_at' => now(),
            'status' => $hasPriorDevice ? 'pending_admin_approval' : 'approved',
            'approved_at' => $hasPriorDevice ? null : now(),
            'last_used_at' => $hasPriorDevice ? null : now(),
        ])->save();

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        RateLimiter::clear($key);

        if ($hasPriorDevice) {
            $request->session()->forget('student_device_request_id');
            return redirect()->route('login')->with('warning', 'Mobile number verified. This is an additional device, so an administrator must approve it before you can sign in.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        DeviceSession::register($request, $user, $device);
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
        if ($device->status !== 'pending_admin_approval' || ! $device->phone_verified_at) {
            return back()->with('error', 'Only phone-verified pending devices can be approved.');
        }

        $device->forceFill([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejected_at' => null,
        ])->save();

        return back()->with('success', 'Student device approved. The student can now sign in from this device.');
    }

    public function reject(StudentDevice $device): RedirectResponse
    {
        if ($device->status !== 'pending_admin_approval') {
            return back()->with('error', 'This device is not awaiting approval.');
        }

        $device->forceFill(['status' => 'rejected', 'rejected_at' => now()])->save();

        return back()->with('success', 'Additional device request rejected.');
    }

    public function revoke(StudentDevice $device): RedirectResponse
    {
        if ($device->status !== 'approved') {
            return back()->with('error', 'Only an approved device can be revoked.');
        }

        $device->forceFill(['status' => 'revoked'])->save();

        return back()->with('success', 'Device access revoked. Its next login attempt will require approval again.');
    }

    public function beginLogin(Request $request, SmsGateway $sms): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower($validated['email']))->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        if (! $user->phone_number) {
            return back()->withErrors(['email' => 'A verified mobile number is required for secure login. Please contact the academy administrator to add one.'])->onlyInput('email');
        }

        $deviceToken = $request->cookie('lms_trusted_device') ?: $request->cookie('lms_student_device');
        if (! is_string($deviceToken) || ! preg_match('/^[a-f0-9]{64}$/', $deviceToken)) {
            $deviceToken = bin2hex(random_bytes(32));
        }
        $deviceHash = hash('sha256', $deviceToken);

        $device = StudentDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->first();

        if ($device?->status === 'approved' && $device->phone_verified_at) {
            $device->forceFill(['last_used_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();
            DeviceSession::register($request, $user, $device);

            return redirect()->intended('/dashboard')
                ->withCookie(cookie('lms_trusted_device', $deviceToken, 60 * 24 * 365, '/', null, true, true, false, 'lax'));
        }

        if (! $sms->isConfigured()) {
            return back()->withErrors(['email' => 'Secure login verification needs the academy SMS service. Please contact the administrator.'])->onlyInput('email');
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
                'approved_by' => null,
                'approved_at' => null,
                'rejected_at' => null,
            ]
        );

        try {
            $sms->send($user->phone_number, "Alom's English Center: Your secure login verification code is {$otp}. It expires in 10 minutes.");
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
            ->withCookie(cookie('lms_trusted_device', $deviceToken, 60 * 24 * 365, '/', null, true, true, false, 'lax'));
    }

    private function deviceName(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);
        $browser = str_contains($agent, 'edg/') ? 'Edge' : (str_contains($agent, 'chrome/') ? 'Chrome' : (str_contains($agent, 'firefox/') ? 'Firefox' : (str_contains($agent, 'safari/') ? 'Safari' : 'Unknown browser')));
        $platform = str_contains($agent, 'android') ? 'Android' : (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') ? 'iOS' : (str_contains($agent, 'windows') ? 'Windows' : (str_contains($agent, 'mac os') ? 'macOS' : (str_contains($agent, 'linux') ? 'Linux' : 'Unknown device'))));

        return $platform.' · '.$browser;
    }
}
