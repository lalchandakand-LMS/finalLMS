<?php

namespace App\Http\Controllers;

use App\Models\DeviceSession;
use App\Models\User;
use App\Services\Security\AdminTotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AdminMfaController extends Controller
{

    public function bootstrap(Request $request): Response
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        $expiresAt = $request->session()->get('admin_mfa_bootstrap_expires_at');
        $emailHash = $request->session()->get('admin_mfa_bootstrap_email_hash');
        if (! is_string($request->session()->get('admin_mfa_bootstrap_code_hash'))
            || ! is_string($emailHash)
            || ! hash_equals($emailHash, hash('sha256', strtolower($admin->email)))
            || ! is_numeric($expiresAt)
            || (int) $expiresAt < now()->timestamp) {
            $this->forgetPending($request);

            return redirect()->route('login')->with('error', 'The email verification code expired. Please sign in again.');
        }

        return response()->view('auth.admin-mfa-bootstrap', ['email' => $admin->email])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function verifyBootstrap(Request $request): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        $expiresAt = $request->session()->get('admin_mfa_bootstrap_expires_at');
        $codeHash = $request->session()->get('admin_mfa_bootstrap_code_hash');
        $emailHash = $request->session()->get('admin_mfa_bootstrap_email_hash');
        if (! is_string($codeHash)
            || ! is_string($emailHash)
            || ! hash_equals($emailHash, hash('sha256', strtolower($admin->email)))
            || ! is_numeric($expiresAt)
            || (int) $expiresAt < now()->timestamp) {
            $this->forgetPending($request);

            return redirect()->route('login')->with('error', 'The email verification code expired. Please sign in again.');
        }

        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $key = 'admin-mfa-bootstrap:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please sign in again later.']);
        }

        if (! Hash::check($validated['code'], $codeHash)) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'The email verification code is invalid or expired.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget(['admin_mfa_bootstrap_code_hash', 'admin_mfa_bootstrap_expires_at', 'admin_mfa_bootstrap_email_hash']);
        $request->session()->put('admin_mfa_bootstrap_verified_at', now()->timestamp);

        return redirect()->route('admin.mfa.setup');
    }

    public function setup(Request $request, AdminTotpService $totp): Response
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        if ($admin->admin_totp_enabled_at && $admin->admin_totp_secret) {
            return redirect()->route('admin.mfa.challenge');
        }

        if (! $this->hasFreshBootstrapVerification($request)) {
            return redirect()->route('admin.mfa.bootstrap');
        }

        $hasEnabledAt = $admin->admin_totp_enabled_at !== null;
        $hasSecret = is_string($admin->admin_totp_secret) && $admin->admin_totp_secret !== '';
        if ($hasEnabledAt !== $hasSecret) {
            $this->forgetPending($request);

            return redirect()->route('login')->with('error', 'Administrator MFA needs secure account recovery. Contact the system owner.');
        }

        $secret = $request->session()->get('admin_mfa_setup_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = $totp->generateSecret();
            $request->session()->put('admin_mfa_setup_secret', $secret);
        }

        return response()->view('auth.admin-mfa-setup', [
            'secret' => $secret,
            'email' => $admin->email,
            'provisioningUri' => $totp->provisioningUri($secret, $admin->email),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function confirmSetup(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        if (! $this->hasFreshBootstrapVerification($request)) {
            return redirect()->route('admin.mfa.bootstrap');
        }

        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $key = 'admin-mfa-setup:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please sign in again later.']);
        }

        $secret = $request->session()->get('admin_mfa_setup_secret');
        if (! is_string($secret) || $secret === '') {
            $this->forgetPending($request);

            return redirect()->route('login')->with('error', 'The MFA setup expired. Please sign in again.');
        }

        $step = $totp->verify($secret, $validated['code']);
        if ($step === null) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'The authenticator code is invalid or expired.']);
        }

        $recoveryCodes = $totp->generateRecoveryCodes();
        $recoveryHashes = array_map($totp->recoveryCodeHash(...), $recoveryCodes);

        $enabled = DB::transaction(function () use ($admin, $secret, $step, $recoveryHashes): bool {
            $locked = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
            if ($locked->admin_totp_enabled_at || $locked->admin_totp_secret) {
                return false;
            }

            $locked->forceFill([
                'admin_totp_secret' => $secret,
                'admin_totp_enabled_at' => now(),
                'admin_totp_recovery_codes' => $recoveryHashes,
                'admin_totp_last_used_step' => $step,
            ])->save();

            return true;
        });

        if (! $enabled) {
            return redirect()->route('admin.mfa.challenge')->with('info', 'Administrator MFA is already enabled. Verify your authenticator code.');
        }

        RateLimiter::clear($key);
        $admin->refresh();
        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('admin_mfa_verified_at', now()->timestamp);
        DeviceSession::register($request, $admin);
        $this->forgetPending($request);
        $request->session()->flash('admin_mfa_recovery_codes', $recoveryCodes);

        return redirect()->intended('/dashboard')->with('success', 'Administrator MFA is enabled. Save your recovery codes in a secure place.');
    }

    public function challenge(Request $request): Response
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        if (! $admin->admin_totp_enabled_at || ! $admin->admin_totp_secret) {
            return redirect()->route('admin.mfa.setup');
        }

        return response()->view('auth.admin-mfa-challenge', ['email' => $admin->email])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function verify(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login')->with('error', 'The administrator verification session expired. Please sign in again.');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);
        $hasTotp = filled($validated['code'] ?? null);
        $hasRecovery = filled($validated['recovery_code'] ?? null);
        if ($hasTotp === $hasRecovery) {
            return back()->withErrors(['code' => 'Enter either an authenticator code or one recovery code.']);
        }

        $key = 'admin-mfa-challenge:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please sign in again later.']);
        }

        $verified = DB::transaction(function () use ($admin, $validated, $hasTotp, $totp): bool {
            $locked = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
            if (! $locked->admin_totp_enabled_at || ! $locked->admin_totp_secret) {
                return false;
            }

            if ($hasTotp) {
                $step = $totp->verify(
                    (string) $locked->admin_totp_secret,
                    (string) $validated['code'],
                    now()->timestamp,
                    $locked->admin_totp_last_used_step,
                );
                if ($step === null) {
                    return false;
                }

                $locked->forceFill(['admin_totp_last_used_step' => $step])->save();

                return true;
            }

            $storedHashes = $locked->admin_totp_recovery_codes ?? [];
            $submittedHash = $totp->recoveryCodeHash((string) $validated['recovery_code']);
            $index = null;
            foreach ($storedHashes as $candidateIndex => $storedHash) {
                if (is_string($storedHash) && hash_equals($storedHash, $submittedHash)) {
                    $index = $candidateIndex;
                    break;
                }
            }

            if ($index === null) {
                return false;
            }

            unset($storedHashes[$index]);
            $locked->forceFill(['admin_totp_recovery_codes' => array_values($storedHashes)])->save();

            return true;
        });

        if (! $verified) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'The authenticator or recovery code is invalid or already used.']);
        }

        RateLimiter::clear($key);
        $admin->refresh();
        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('admin_mfa_verified_at', now()->timestamp);
        DeviceSession::register($request, $admin);
        $this->forgetPending($request);

        return redirect()->intended('/dashboard')->with('success', 'Administrator sign-in verified.');
    }

    private function pendingAdmin(Request $request): ?User
    {
        $id = $request->session()->get('pending_admin_mfa_user_id');
        $startedAt = $request->session()->get('pending_admin_mfa_started_at');

        if (! is_numeric($id) || ! is_numeric($startedAt) || (int) $startedAt < now()->subMinutes(10)->timestamp) {
            $this->forgetPending($request);

            return null;
        }

        $admin = User::query()->whereKey((int) $id)->where('role', 'admin')->first();
        if (! $admin) {
            $this->forgetPending($request);
        }

        return $admin;
    }

    private function hasFreshBootstrapVerification(Request $request): bool
    {
        $verifiedAt = $request->session()->get('admin_mfa_bootstrap_verified_at');

        return is_numeric($verifiedAt)
            && (int) $verifiedAt >= now()->subMinutes(10)->timestamp;
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([
            'pending_admin_mfa_user_id',
            'pending_admin_mfa_started_at',
            'admin_mfa_setup_secret',
            'admin_mfa_bootstrap_code_hash',
            'admin_mfa_bootstrap_expires_at',
            'admin_mfa_bootstrap_email_hash',
            'admin_mfa_bootstrap_verified_at',
        ]);
    }
}
