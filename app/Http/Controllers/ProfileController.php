<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone_number' => ['sometimes', 'nullable', 'regex:/^\+[1-9][0-9]{7,14}$/', 'unique:users,phone_number,' . $user->id],
            'sms_marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        $emailChanged = $user->email !== $validated['email'];
        $phoneChanged = array_key_exists('phone_number', $validated) && $user->phone_number !== $validated['phone_number'];
        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($phoneChanged) {
            $user->forceFill([
                'phone_verified_at' => null,
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
            ]);
        }

        $user->save();\n\n        if ($phoneChanged) {\n            StudentDevice::query()->where('user_id', $user->id)->where('status', 'approved')->update(['status' => 'revoked', 'updated_at' => now()]);\n        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', 'Email changed. Verify your new address to continue.');
        }

        return redirect()->route('dashboard')->with('success', 'Profile updated successfully.');
    }
}
