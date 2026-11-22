<?php

namespace App\Http\Controllers;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        $this->rememberContinuePath($request);

        if (Auth::check()) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.login');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        $this->rememberContinuePath($request);

        if (Auth::check()) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', 'unique:users,phone_number'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => $validated['password'],
            'role' => 'student',
        ]);

        event(new Registered($user));

        // Do not create an authenticated session until the student's first device has passed SMS verification.
        return redirect()->route('login')
            ->with('success', 'Student account created. Sign in to verify your mobile number and register this device.')
            ->with('meta_pixel_event', 'CompleteRegistration');
    }

    public function showPasswordResetRequestForm(): View
    {
        return view('auth.login', ['showPasswordResetRequest' => true]);
    }

    public function showPasswordResetForm(Request $request, string $token): View
    {
        return view('auth.login', [
            'passwordResetToken' => $token,
            'passwordResetEmail' => (string) $request->query('email', ''),
        ]);
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $status = PasswordBroker::sendResetLink($data);

        return $status === PasswordBroker::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $status = PasswordBroker::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            event(new PasswordReset($user));
        });

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            DeviceSession::query()
                ->where('user_id', Auth::id())
                ->where('session_hash', $request->session()->get('device_session_hash', hash('sha256', $request->session()->getId())))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoke_reason' => 'User logged out', 'updated_at' => now()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function rememberContinuePath(Request $request): void
    {
        $continue = $request->query('continue');

        if (! is_string($continue)
            || ! str_starts_with($continue, '/')
            || str_starts_with($continue, '//')
            || str_contains($continue, chr(92))
            || preg_match('/[\r\n]/', $continue)) {
            return;
        }

        $request->session()->put('url.intended', $continue);
    }
}
