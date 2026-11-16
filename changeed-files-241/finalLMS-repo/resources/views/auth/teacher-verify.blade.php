@extends('layouts.app')
@section('title', 'Verify teacher mobile')
@section('content')
<div class="auth-box">
    <h2>Verify mobile number</h2>
    <p class="muted">A 6-digit OTP was sent to {{ $application->phone_number }}. It expires in 10 minutes.</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('teacher.apply.verify.post') }}">@csrf
        <label>SMS verification code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
        <button class="btn primary" type="submit" style="width:100%;">Verify mobile</button>
    </form>
</div>
@endsection