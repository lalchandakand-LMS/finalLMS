@extends('layouts.app')

@section('title', 'Create account | ' . config('app.name'))

@section('content')
    <div class="auth-box">
        <h1 style="margin-top:0;">Create your student account</h1>
        <p class="muted">Verify your email address to access your learning dashboard.</p>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <label>Full name<input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required></label>
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label>Password<input type="password" name="password" autocomplete="new-password" required></label>
            <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <button type="submit" class="btn primary" style="width:100%; margin-top:12px;">Create account</button>
        </form>

        <p class="muted" style="margin-bottom:0;">Already registered? <a href="{{ route('login') }}">Login</a></p>
    </div>
@endsection