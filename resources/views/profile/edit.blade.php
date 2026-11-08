@extends('layouts.app')

@section('title', 'Edit Profile | LMS Academy')

@section('content')
    @php
        $announcementSlides = \App\Models\DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', auth()->user()->role])
            ->with('image')->orderByDesc('priority')->latest()->take(8)->get();
    @endphp
    @include('components.dashboard-announcement-slider')
    <div class="page-header">
        <div>
            @if($user->isStudent())
                <a class="btn secondary" href="{{ route('student.admission.edit') }}">{{ __('Update full admission profile') }}</a>
                <a class="btn secondary" href="{{ route('student.course-sessions.index') }}">{{ __('My course sessions') }}</a>
            @endif
            <span class="badge info">Profile</span>
            <h1 style="margin: 12px 0 0;">{{ __('Edit profile') }}</h1>
        </div>
    </div>

    <div class="panel" style="max-width: 720px; margin: 0 auto;">
        @if($user->isStudent() && $user->admissionProfile)
            <p><a class="btn secondary" href="{{ route('student.admission.edit') }}">{{ __('Update full admission profile, guardian contacts and address') }}</a></p>
        @endif
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf

            <label>
                {{ __('Full name') }}
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </label>

            <label>
                {{ __('Email address') }}
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </label>

            <label>
                {{ __('Phone number (E.164 format, for example +8801XXXXXXXXX)') }}
                <input type="tel" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" pattern="\+[1-9][0-9]{7,14}" placeholder="+8801XXXXXXXXX">
            </label>

            <input type="hidden" name="sms_marketing_opt_in" value="0">
            <label style="display:flex; align-items:flex-start; gap:10px;">
                <input type="checkbox" name="sms_marketing_opt_in" value="1" style="width:auto; margin-top:4px;" @checked(old('sms_marketing_opt_in', $user->sms_marketing_opt_in))>
                <span>{{ __('Send me course announcements and offers by SMS. I can opt out from this profile at any time.') }}</span>
            </label>

            <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                <button type="submit" class="btn primary">{{ __('Save profile') }}</button>
                <a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Cancel') }}</a>
            </div>
        </form>

        @if($user->phone_number)
            <hr style="border:0; border-top:1px solid var(--border); margin:24px 0;">
            @if($user->phone_verified_at)
                <span class="badge success">{{ __('Phone verified') }}</span>
            @else
                <h2>{{ __('Verify phone number') }}</h2>
                <form method="POST" action="{{ route('phone.verification.send') }}">
                    @csrf
                    <button type="submit" class="btn secondary">{{ __('Send verification code') }}</button>
                </form>
                @if($user->phone_verification_code)
                    <form method="POST" action="{{ route('phone.verification.verify') }}" style="margin-top:16px;">
                        @csrf
                        <label for="phone-code">{{ __('Six-digit code') }}</label>
                        <input id="phone-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                        <button type="submit" class="btn primary" style="margin-top:12px;">{{ __('Verify phone') }}</button>
                    </form>
                @endif
            @endif
        @endif
    </div>
@endsection
