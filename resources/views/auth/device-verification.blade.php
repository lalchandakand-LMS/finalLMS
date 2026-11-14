@extends('layouts.app')

@section('title', 'Verify device | ' . config('app.name'))

@section('content')
<div class="auth-box">
    <span class="badge info">Account security</span>
    <h2 style="margin-top:12px;">Verify this device</h2>
    @if($device->status === 'pending_admin_approval')
        <div class="panel">
            <strong>Mobile number verified.</strong>
            <p class="muted">This is an additional device. Your sign-in will be enabled after an academy administrator approves this request.</p>
            <p><strong>Device:</strong> {{ $device->device_name }}</p>
        </div>
        <a href="{{ route('login') }}" class="btn secondary" style="width:100%;margin-top:14px;">Back to login</a>
    @else
        <p class="muted">We sent a 6-digit verification code to the mobile number saved in your account.</p>
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('student-device.verify-phone') }}" style="margin-top:20px;">
            @csrf
            <label for="code">6-digit SMS code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn primary" type="submit" style="width:100%;margin-top:14px;">Verify mobile number</button>
        </form>
        <p class="muted" style="font-size:.85rem;">The code expires in 10 minutes. If you did not request this login, do not share the code with anyone.</p>
    @endif
</div>
@endsection
