@extends('layouts.app')

@section('title', __('Verification Center') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Verification Center') }}</h1>
            <p class="muted">{{ __('Monitor email and mobile verification for students, guardians, teachers and branch managers. Verification can only be completed by the account holder.') }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn secondary">{{ __('Manage users') }}</a>
    </div>

    <div class="grid grid-3" style="margin-bottom:20px;">
        <div class="panel"><small class="muted">{{ __('Accounts') }}</small><h2>{{ number_format($totals['accounts']) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Email verified') }}</small><h2>{{ number_format($totals['email_verified']) }}</h2><small class="muted">{{ __('Pending') }}: {{ number_format($totals['email_pending']) }}</small></div>
        <div class="panel"><small class="muted">{{ __('Mobile verified') }}</small><h2>{{ number_format($totals['phone_verified']) }}</h2><small class="muted">{{ __('Pending') }}: {{ number_format($totals['phone_pending']) }} · {{ __('Missing') }}: {{ number_format($totals['phone_missing']) }}</small></div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('admin.verification-center.index') }}" class="grid grid-3" style="margin-bottom:20px;">
            <div>
                <label for="verification-search">{{ __('Search name, email or mobile') }}</label>
                <input id="verification-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120">
            </div>
            <div>
                <label for="verification-role">{{ __('Account type') }}</label>
                <select id="verification-role" name="role">
                    <option value="">{{ __('All account types') }}</option>
                    @foreach(['student' => 'Student', 'guardian' => 'Guardian', 'teacher' => 'Teacher', 'branch_manager' => 'Branch Manager'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="email-status">{{ __('Email verification') }}</label>
                <select id="email-status" name="email_status">
                    <option value="">{{ __('All') }}</option>
                    <option value="verified" @selected(($filters['email_status'] ?? '') === 'verified')>{{ __('Verified') }}</option>
                    <option value="pending" @selected(($filters['email_status'] ?? '') === 'pending')>{{ __('Pending') }}</option>
                </select>
            </div>
            <div>
                <label for="phone-status">{{ __('Mobile verification') }}</label>
                <select id="phone-status" name="phone_status">
                    <option value="">{{ __('All') }}</option>
                    <option value="verified" @selected(($filters['phone_status'] ?? '') === 'verified')>{{ __('Verified') }}</option>
                    <option value="pending" @selected(($filters['phone_status'] ?? '') === 'pending')>{{ __('Pending') }}</option>
                    <option value="missing" @selected(($filters['phone_status'] ?? '') === 'missing')>{{ __('Mobile missing') }}</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;align-items:end;">
                <button class="btn primary" type="submit">{{ __('Filter') }}</button>
                <a class="btn secondary" href="{{ route('admin.verification-center.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:980px;">
                <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                    <th style="padding:12px 10px;">{{ __('Account') }}</th>
                    <th style="padding:12px 10px;">{{ __('Type / Branch') }}</th>
                    <th style="padding:12px 10px;">{{ __('Email status') }}</th>
                    <th style="padding:12px 10px;">{{ __('Mobile status') }}</th>
                    <th style="padding:12px 10px;">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 10px;">
                            <strong>{{ $user->name }}</strong><br>
                            <small class="muted">{{ $user->email }}</small><br>
                            <small class="muted">{{ $user->phone_number ?: __('No mobile number') }}</small>
                        </td>
                        <td style="padding:12px 10px;">{{ __(ucwords(str_replace('_', ' ', $user->role))) }}<br><small class="muted">{{ $user->branch?->name ?? '—' }}</small></td>
                        <td style="padding:12px 10px;">
                            @if($user->email_verified_at)
                                <span class="badge success">{{ __('Verified') }}</span><br><small class="muted">{{ $user->email_verified_at->format('d M Y, h:i A') }}</small>
                            @else
                                <span class="badge warning">{{ __('Pending') }}</span>
                            @endif
                        </td>
                        <td style="padding:12px 10px;">
                            @if(!$user->phone_number)
                                <span class="badge warning">{{ __('Missing') }}</span>
                            @elseif($user->phone_verified_at)
                                <span class="badge success">{{ __('Verified') }}</span><br><small class="muted">{{ $user->phone_verified_at->format('d M Y, h:i A') }}</small>
                            @else
                                <span class="badge warning">{{ __('Pending') }}</span>
                                @if($user->phone_verification_expires_at?->isFuture())
                                    <br><small class="muted">{{ __('Code active until') }} {{ $user->phone_verification_expires_at->format('h:i A') }}</small>
                                @endif
                            @endif
                        </td>
                        <td style="padding:12px 10px;">
                            @unless($user->email_verified_at)
                                <form method="POST" action="{{ route('admin.verification-center.email', $user) }}" style="display:inline-block;margin:0 6px 6px 0;">
                                    @csrf<button class="btn secondary" type="submit">{{ __('Resend email link') }}</button>
                                </form>
                            @endunless
                            @if($user->phone_number && !$user->phone_verified_at)
                                <form method="POST" action="{{ route('admin.verification-center.phone', $user) }}" style="display:inline-block;margin:0 6px 6px 0;">
                                    @csrf<button class="btn secondary" type="submit">{{ __('Send mobile OTP') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No accounts match these filters.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $users->links() }}</div>
    </div>
@endsection
