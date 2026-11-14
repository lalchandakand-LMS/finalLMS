@extends('layouts.app')

@section('title', __('Device sessions') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin only') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Device sessions') }}</h1>
        <p class="muted">{{ __('Review recent sign-ins and revoke sessions that should no longer have access.') }}</p>
    </div>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="panel"><strong>{{ __('Active') }}</strong><h2>{{ $counts['active'] }}</h2></div>
    <div class="panel"><strong>{{ __('Revoked') }}</strong><h2>{{ $counts['revoked'] }}</h2></div>
    <div class="panel"><strong>{{ __('Expired') }}</strong><h2>{{ $counts['expired'] }}</h2></div>
</div>

<div class="panel">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <label>{{ __('Role') }}
            <select name="role">
                <option value="">{{ __('All roles') }}</option>
                @foreach(['admin', 'teacher', 'student', 'guardian', 'branch_manager', 'accountant'] as $role)
                    <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('Status') }}
            <select name="status">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['active', 'expired', 'revoked'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn primary" type="submit">{{ __('Filter') }}</button>
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:900px;border-collapse:collapse;">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                <th style="padding:10px;">{{ __('Account') }}</th><th>{{ __('Role / branch') }}</th><th>{{ __('Device') }}</th><th>{{ __('IP address') }}</th><th>{{ __('Last activity') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th>
            </tr></thead>
            <tbody>
            @forelse($sessions as $session)
                @php($isActive = ! $session->revoked_at && $session->last_seen_at?->gte(now()->subMinutes((int) config('session.lifetime', 120))))
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 10px;">{{ $session->user->name }}<br><small class="muted">{{ $session->user->email }}</small></td>
                    <td>{{ ucfirst(str_replace('_', ' ', $session->user->role)) }}<br><small class="muted">{{ $session->user->branch?->name ?? '—' }}</small></td>
                    <td>{{ $session->device_name ?? '—' }}</td>
                    <td>{{ $session->ip_address ?? '—' }}</td>
                    <td>{{ $session->last_seen_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td><span class="badge {{ $session->revoked_at ? 'warning' : ($isActive ? 'success' : 'secondary') }}">{{ $session->revoked_at ? __('Revoked') : ($isActive ? __('Active') : __('Expired')) }}</span></td>
                    <td>
                        @if(! $session->revoked_at && ! ($session->user_id === auth()->id() && hash_equals($session->session_hash, hash('sha256', request()->session()->getId()))))
                            <form method="POST" action="{{ route('admin.security.sessions.revoke', $session) }}" onsubmit="return confirm('{{ __('Sign out this session?') }}')">
                                @csrf @method('PATCH')
                                <button class="btn secondary" type="submit">{{ __('Revoke') }}</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted" style="padding:18px;">{{ __('No tracked sessions yet. Sessions are recorded when users sign in again.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $sessions->links() }}</div>
</div>
@endsection
