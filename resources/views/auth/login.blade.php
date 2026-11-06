@extends('layouts.app')

@section('title', 'Login | LMS Academy')

@section('content')
    <div class="auth-box">
        <h2 style="margin-top: 0; font-size: 2rem;">Welcome back</h2>
        <p class="muted">Login to access your learning dashboard.</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" style="margin-top: 24px;">
            @csrf

            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label>
                Password
                <input type="password" name="password" required>
            </label>

            <button type="submit" class="btn primary" style="width:100%; margin-top: 12px;">Login</button>
        </form>

        <p class="muted" style="margin-bottom:0;">New to {{ config('app.name') }}? <a href="{{ route('register') }}">Create a student account</a></p>
    </div>
@endsection
