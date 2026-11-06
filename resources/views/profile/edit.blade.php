@extends('layouts.app')

@section('title', 'Edit Profile | LMS Academy')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Profile</span>
            <h1 style="margin: 12px 0 0;">Edit profile</h1>
        </div>
    </div>

    <div class="panel" style="max-width: 720px; margin: 0 auto;">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf

            <label>
                Full name
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </label>

            <label>
                Email address
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </label>

            <label>
                Phone number (E.164 format, for example +8801XXXXXXXXX)
                <input type="tel" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" pattern="\+[1-9][0-9]{7,14}" placeholder="+8801XXXXXXXXX">
            </label>

            <input type="hidden" name="sms_marketing_opt_in" value="0">
            <label style="display:flex; align-items:flex-start; gap:10px;">
                <input type="checkbox" name="sms_marketing_opt_in" value="1" style="width:auto; margin-top:4px;" @checked(old('sms_marketing_opt_in', $user->sms_marketing_opt_in))>
                <span>Send me course announcements and offers by SMS. I can opt out from this profile at any time.</span>
            </label>

            <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                <button type="submit" class="btn primary">Save profile</button>
                <a href="{{ route('dashboard') }}" class="btn secondary">Cancel</a>
            </div>
        </form>

        @if($user->phone_number)
            <hr style="border:0; border-top:1px solid var(--border); margin:24px 0;">
            @if($user->phone_verified_at)
                <span class="badge success">Phone verified</span>
            @else
                <h2>Verify phone number</h2>
                <form method="POST" action="{{ route('phone.verification.send') }}">
                    @csrf
                    <button type="submit" class="btn secondary">Send verification code</button>
                </form>
                @if($user->phone_verification_code)
                    <form method="POST" action="{{ route('phone.verification.verify') }}" style="margin-top:16px;">
                        @csrf
                        <label for="phone-code">Six-digit code</label>
                        <input id="phone-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                        <button type="submit" class="btn primary" style="margin-top:12px;">Verify phone</button>
                    </form>
                @endif
            @endif
        @endif
    </div>
@endsection
