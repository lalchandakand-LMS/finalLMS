@extends('layouts.app')
@section('title', __('Device & session security'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Account protection') }}</span><h1>{{ __('Devices & active logins') }}</h1><p class="muted">{{ __('Review login devices for every account and remotely sign out a lost or unknown device.') }}</p></div></div>
<div class="dash-summary">
    <div class="summary-box"><span class="muted">{{ __('Active sessions') }}</span><strong>{{ $counts['active'] }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Signed-out sessions') }}</span><strong>{{ $counts['revoked'] }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Expired / idle sessions') }}</span><strong>{{ $counts['expired'] }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Trusted devices') }}</span><strong>{{ $counts['devices'] }}</strong></div>
</div>
<div class="panel" style="margin-bottom:18px;">
<form method="GET" class="grid grid-3" style="align-items:end;">
    <label>{{ __('Account role') }}<select name="role"><option value="">{{ __('All roles') }}</option>@foreach(['admin'=>'Admin','branch_manager'=>'Branch','accountant'=>'Accountant','teacher'=>'Teacher','student'=>'Student','guardian'=>'Guardian'] as $value=>$label)<option value="{{ $value }}" @selected(request('role')===$value)>{{ __($label) }}</option>@endforeach</select></label>
    <label>{{ __('Session status') }}<select name="status"><option value="">{{ __('All') }}</option><option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option><option value="revoked" @selected(request('status')==='revoked')>{{ __('Signed out') }}</option><option value="expired" @selected(request('status')==='expired')>{{ __('Expired / idle') }}</option></select></label>
    <button class="btn primary" type="submit">{{ __('Filter') }}</button>
</form>
</div>
<div class="panel">
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;min-width:900px;">
<thead><tr><th>{{ __('Account') }}</th><th>{{ __('Device / browser') }}</th><th>{{ __('Branch') }}</th><th>{{ __('IP address') }}</th><th>{{ __('Last active') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
<tbody>
@forelse($sessions as $session)
<tr style="border-top:1px solid var(--border);">
<td style="padding:12px 8px;"><strong>{{ $session->user?->name ?? __('Deleted account') }}</strong><br><small class="muted">{{ $session->user?->email }} · {{ ucfirst(str_replace('_',' ',$session->user?->role ?? '')) }}</small></td>
<td style="padding:12px 8px;">{{ $session->device_name ?: __('Unknown device') }}<br><small class="muted">{{ \Illuminate\Support\Str::limit($session->user_agent, 85) }}</small></td>
<td style="padding:12px 8px;">{{ $session->user?->branch?->name ?? '—' }}</td>
<td style="padding:12px 8px;">{{ $session->ip_address ?? '—' }}</td>
<td style="padding:12px 8px;">{{ $session->last_seen_at?->diffForHumans() ?? '—' }}<br><small class="muted">{{ __('Login') }}: {{ $session->logged_in_at?->format('d M Y H:i') }}</small></td>
@php($isExpired = !$session->revoked_at && (!$session->last_seen_at || $session->last_seen_at->lt(now()->subMinutes(config('session.lifetime', 120)))))
<td style="padding:12px 8px;"><span class="badge {{ $session->revoked_at || $isExpired ? 'warning' : 'success' }}">{{ $session->revoked_at ? __('Signed out') : ($isExpired ? __('Expired / idle') : __('Active')) }}</span></td>
<td style="padding:12px 8px;">@if(!$session->revoked_at && !$isExpired)<form method="POST" action="{{ route('admin.security.sessions.revoke', $session) }}" onsubmit="return confirm('{{ __('Sign out this device now?') }}')">@csrf @method('PATCH')<button class="btn secondary" type="submit">{{ __('Log out device') }}</button></form>@elseif($session->revoked_at)<small class="muted">{{ $session->revoked_at->format('d M Y H:i') }}</small>@else<small class="muted">{{ __('Session timeout') }}</small>@endif</td>
</tr>
@empty<tr><td colspan="7" style="padding:20px;text-align:center;" class="muted">{{ __('No device sessions found.') }}</td></tr>@endforelse
</tbody></table></div>
{{ $sessions->links() }}
</div>
@endsection
