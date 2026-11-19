@extends('layouts.app')

@section('title', __('Administrator email verification') . ' | ' . config('app.name'))

@section('content')
<div class="auth-card" style="max-width:520px;margin:32px auto;padding:28px;">
    <h1>{{ __('Verify administrator email') }}</h1>
    <p>{{ __('Before enrolling an authenticator, enter the one-time code sent to the verified administrator email address.') }}</p>
    <p class="muted">{{ $email }}</p>
    <form method="POST" action="{{ route('admin.mfa.bootstrap.verify') }}" style="display:grid;gap:14px;">
        @csrf
        <label for="code">{{ __('6-digit email verification code') }}</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit">{{ __('Verify email and continue') }}</button>
    </form>
    <p class="muted">{{ __('The code expires in 10 minutes. If it expires, sign in again to request a new code.') }}</p>
</div>
@endsection
