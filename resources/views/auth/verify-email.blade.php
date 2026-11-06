@extends('layouts.app')

@section('title', 'Verify email | ' . config('app.name'))

@section('content')
    <div class="auth-box">
        <span class="badge warning">Email verification</span>
        <h1 style="margin-top:14px;">Check your inbox</h1>
        <p class="muted">We sent a verification link to {{ auth()->user()->email }}. Verify your address before continuing.</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn primary" style="width:100%;">Resend verification email</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;">
            @csrf
            <button type="submit" class="btn secondary" style="width:100%;">Logout</button>
        </form>
    </div>
@endsection