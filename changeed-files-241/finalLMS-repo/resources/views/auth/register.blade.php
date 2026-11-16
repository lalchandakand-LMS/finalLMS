@extends('layouts.app')

@section('title', 'Create account | ' . config('app.name'))

@section('content')
    <div class="auth-box">
        <h1 style="margin-top:0;">{{ __('Create your student account') }}</h1>
        <p class="muted">{{ __('Verify your email address to access your learning dashboard. A mobile number is required for secure device sign-in.') }}</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <label>{{ __('Full name') }}<input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required></label>
            <label>{{ __('Email address') }}<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label>{{ __('Mobile number (E.164 format, for example +8801XXXXXXXXX)') }}<input type="tel" name="phone_number" value="{{ old('phone_number') }}" autocomplete="tel" pattern="[+][1-9][0-9]{7,14}" placeholder="+8801XXXXXXXXX" required></label>
            <label>{{ __('Password') }}<input type="password" name="password" autocomplete="new-password" required></label>
            <label>{{ __('Confirm password') }}<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <button type="submit" class="btn primary" style="width:100%; margin-top:12px;">{{ __('Create account') }}</button>
        </form>

        <p class="muted" style="margin-bottom:0;">{{ __('Already registered?') }} <a href="{{ route('login') }}">{{ __('Login') }}</a></p>
    </div>
@endsection