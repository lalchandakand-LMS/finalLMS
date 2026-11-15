<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class AdminVerificationController extends Controller
{
    private const ROLES = ['student', 'guardian', 'teacher', 'branch_manager'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:student,guardian,teacher,branch_manager'],
            'email_status' => ['nullable', 'in:verified,pending'],
            'phone_status' => ['nullable', 'in:verified,pending,missing'],
        ]);

        $base = User::query()->whereIn('role', self::ROLES);
        $totals = [
            'accounts' => (clone $base)->count(),
            'email_verified' => (clone $base)->whereNotNull('email_verified_at')->count(),
            'email_pending' => (clone $base)->whereNull('email_verified_at')->count(),
            'phone_verified' => (clone $base)->whereNotNull('phone_verified_at')->count(),
            'phone_pending' => (clone $base)->whereNotNull('phone_number')->whereNull('phone_verified_at')->count(),
            'phone_missing' => (clone $base)->whereNull('phone_number')->count(),
        ];

        $users = User::query()
            ->with('branch:id,name')
            ->whereIn('role', self::ROLES)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(($filters['email_status'] ?? null) === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when(($filters['email_status'] ?? null) === 'pending', fn ($query) => $query->whereNull('email_verified_at'))
            ->when(($filters['phone_status'] ?? null) === 'verified', fn ($query) => $query->whereNotNull('phone_verified_at'))
            ->when(($filters['phone_status'] ?? null) === 'pending', fn ($query) => $query->whereNotNull('phone_number')->whereNull('phone_verified_at'))
            ->when(($filters['phone_status'] ?? null) === 'missing', fn ($query) => $query->whereNull('phone_number'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.verification-center.index', compact('users', 'totals', 'filters'));
    }

    public function resendEmail(User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, self::ROLES, true), 404);

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', 'This email address is already verified.');
        }

        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            return back()->with('error', 'Email delivery is not configured. Set up SMTP or another real mail provider in the server environment first.');
        }

        $key = 'admin-verification-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Email verification reminders are rate limited. Try again later.');
        }

        RateLimiter::hit($key, 600);
        $user->sendEmailVerificationNotification();

        return back()->with('success', 'A new email verification link was sent to the account email address.');
    }

    public function sendPhoneCode(User $user, SmsGateway $sms): RedirectResponse
    {
        abort_unless(in_array($user->role, self::ROLES, true), 404);

        if (! $user->phone_number) {
            return back()->with('error', 'This account does not have a mobile number yet.');
        }

        if ($user->phone_verified_at) {
            return back()->with('info', 'This mobile number is already verified.');
        }

        $key = 'admin-verification-phone:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Mobile verification messages are rate limited. Try again later.');
        }

        RateLimiter::hit($key, 600);
        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'phone_verification_code' => Hash::make($code),
            'phone_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            $sms->send($user->phone_number, "Your AlomsEnglishCenter verification code is {$code}. It expires in 10 minutes.");
        } catch (Throwable $exception) {
            report($exception);
            RateLimiter::clear($key);
            $user->forceFill([
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
            ])->save();

            return back()->with('error', 'SMS could not be sent. Check the SMS provider configuration and try again.');
        }

        return back()->with('success', 'A new mobile verification code was sent to the account phone number.');
    }
}
