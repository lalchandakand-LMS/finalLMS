@extends('layouts.app')

@section('title', __('Secure administrator setup') . ' | ' . config('app.name'))

@section('content')
<div class="auth-card" style="max-width:620px;margin:32px auto;padding:28px;">
    <h1>{{ __('Set up administrator two-factor authentication') }}</h1>
    <p>{{ __('Add this account to an authenticator app that supports time-based one-time passwords (TOTP). You can enter the key manually if QR scanning is unavailable.') }}</p>
    <p><strong>{{ __('Account') }}:</strong> {{ auth()->user()?->email ?? old('email') }}</p>
    <p><strong>{{ __('Setup key') }}:</strong></p>
    <p><code style="display:block;overflow-wrap:anywhere;padding:12px;background:#f3f4f6;">{{ $secret }}</code></p>
    <p class="muted">{{ __('Issuer:') }} {{ config('app.name') }}. {{ __('Use a 6-digit code that refreshes every 30 seconds.') }}</p>
    <form method="POST" action="{{ route('admin.mfa.setup.confirm') }}" style="display:grid;gap:14px;">
        @csrf
        <label for="code">{{ __('Enter the 6-digit authenticator code') }}</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit">{{ __('Verify and enable MFA') }}</button>
    </form>
    <p class="muted">{{ __('Your administrator session will not start until this code is verified. Keep your setup key private.') }}</p>
</div>
@endsection
