<?php

namespace App\Http\Controllers;

use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, SmsGateway $sms): RedirectResponse
    {
        $user = $request->user();

        if (! $user->phone_number) {
            return back()->with('error', 'Add a phone number before requesting verification.');
        }

        if ($user->phone_verified_at) {
            return back()->with('info', 'This phone number is already verified.');
        }

        $key = 'phone-verification-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Too many verification codes requested. Please try again later.');
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

            return back()->with('error', 'SMS could not be sent. Please check the SMS provider configuration or try later.');
        }

        return back()->with('success', 'A phone verification code has been sent.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        $key = 'phone-verification-check:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many incorrect codes. Request a new code later.');
        }

        if (
            ! $user->phone_verification_code ||
            ! $user->phone_verification_expires_at?->isFuture() ||
            ! Hash::check($validated['code'], $user->phone_verification_code)
        ) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'The verification code is invalid or expired.']);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_expires_at' => null,
        ])->save();
        RateLimiter::clear($key);

        return back()->with('success', 'Phone number verified successfully.');
    }
}