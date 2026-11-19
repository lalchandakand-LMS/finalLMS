@extends('layouts.app')

@section('title', __('Administrator verification') . ' | ' . config('app.name'))

@section('content')
<div class="auth-card" style="max-width:520px;margin:32px auto;padding:28px;">
    <h1>{{ __('Verify administrator sign-in') }}</h1>
    <p>{{ __('Enter the current code from your authenticator app, or use one unused recovery code.') }}</p>
    <p class="muted">{{ $email }}</p>
    <form method="POST" action="{{ route('admin.mfa.verify') }}" style="display:grid;gap:14px;">
        @csrf
        <label for="code">{{ __('Authenticator code') }}</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">
        <p class="muted">{{ __('If you use a recovery code, leave the authenticator field empty.') }}</p>
        <label for="recovery_code">{{ __('Recovery code') }}</label>
        <input id="recovery_code" name="recovery_code" autocomplete="off" maxlength="32">
        @error('code')<p class="error">{{ $message }}</p>@enderror
        @error('recovery_code')<p class="error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit">{{ __('Verify and sign in') }}</button>
    </form>
</div>
@endsection
