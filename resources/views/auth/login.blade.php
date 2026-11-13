@extends('layouts.app')

@section('title', __('Student login') . ' | ' . config('app.name'))

@section('content')
    <style>
        .auth-login-wrap{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);gap:22px;max-width:980px;margin:28px auto;align-items:stretch}
        .auth-login-intro{display:flex;flex-direction:column;justify-content:center;padding:clamp(28px,5vw,54px);border-radius:22px;color:#fff;background:radial-gradient(circle at 85% 12%,#2689a2,transparent 35%),linear-gradient(145deg,#102e52,#0d6476)}
        .auth-login-intro h1{margin:12px 0;color:#fff;font-size:clamp(2rem,4vw,3rem);line-height:1.12}
        .auth-login-intro p{color:#d7eaf2;line-height:1.75}
        .auth-login-benefits{display:grid;gap:12px;margin-top:22px}
        .auth-login-benefits span{display:flex;align-items:center;gap:10px;color:#e7f5f8;font-size:.9rem}
        .auth-login-benefits b{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;color:#0d5365;background:#b8f0df}
        .auth-login-card{padding:clamp(22px,4vw,38px);border:1px solid var(--border);border-radius:22px;background:#fff;box-shadow:0 18px 48px rgba(16,42,76,.09)}
        .auth-login-card h2{margin:0 0 7px;font-size:1.7rem}
        .auth-login-card form{display:grid;gap:15px;margin-top:22px}
        .auth-login-card label{display:grid;gap:7px;font-weight:700;font-size:.9rem}
        .auth-login-card input{min-height:48px}
        .auth-login-submit{width:100%;min-height:49px;margin-top:5px;font-size:1rem}
        .auth-login-foot{margin:18px 0 0;color:var(--muted);font-size:.85rem;line-height:1.65}
        @media(max-width:760px){.auth-login-wrap{grid-template-columns:1fr;margin:10px auto}.auth-login-intro{padding:26px}.auth-login-intro h1{font-size:2rem}}
    </style>

    <div class="auth-login-wrap">
        <section class="auth-login-intro">
            <span style="font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:#9ce7d8;">{{ config('app.name') }}</span>
            <h1>{{ __('Continue your learning journey') }}</h1>
            <p>{{ __('Sign in to access your enrolled courses, lessons, live classes, assignments and learning progress.') }}</p>
            <div class="auth-login-benefits">
                <span><b>✓</b> {{ __('Access your course dashboard') }}</span>
                <span><b>✓</b> {{ __('Continue lessons and track progress') }}</span>
                <span><b>✓</b> {{ __('Secure sign-in with device verification') }}</span>
            </div>
        </section>

        <section class="auth-login-card">
            <h2>{{ __('Welcome back') }}</h2>
            <p class="muted" style="margin:0;">{{ __('Login to access your learning dashboard.') }}</p>

            @if(session('success'))
                <div class="flash success" role="status">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="flash warning" role="status">{{ session('warning') }}</div>
            @endif
            @if(session('error'))
                <div class="flash error" role="alert">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <label>
                    {{ __('Email address') }}
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus>
                </label>
                <label>
                    {{ __('Password') }}
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn primary auth-login-submit">{{ __('Login securely') }} →</button>
            </form>

            <p class="auth-login-foot">{{ __('For student accounts, a new device must pass mobile-number verification. Additional devices require academy administrator approval.') }}</p>
            <p class="auth-login-foot" style="margin-top:12px;">{{ __('New to') }} {{ config('app.name') }}? <a href="{{ route('register') }}">{{ __('Create a student account') }}</a></p>
        </section>
    </div>
@endsection
