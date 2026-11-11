@extends('layouts.app')

@section('title', 'Login | LMS Academy')

@section('content')
    <div class="auth-box">
        <h2 style="margin-top: 0; font-size: 2rem;">{{ __('Welcome back') }}</h2>
        <p class="muted">{{ __('Login to access your learning dashboard.') }}</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" style="margin-top: 24px;">
            @csrf

            <label>
                {{ __('Email') }}
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label>
                {{ __('Password') }}
                <input type="password" name="password" required>
            </label>

            <button type="submit" class="btn primary" style="width:100%; margin-top: 12px;">{{ __('Login') }}</button>
        </form>

        <p class="muted" style="font-size:.88rem;margin-top:14px;">For student, teacher, branch staff, accountant and guardian accounts, each new device requires SMS mobile verification. Additional devices must be approved by an academy administrator. Use a private device and never share your one-time code.</p>
        <p class="muted" style="margin-bottom:0;">New to {{ config('app.name') }}? <a href="{{ route('register') }}">{{ __('Create a student account') }}</a></p>
    </div>
@endsection
