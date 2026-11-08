@extends('layouts.app')
@section('title', __('Deleted accounts').' | '.config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge info">Super Admin</span><h1>{{ __('Deleted accounts') }}</h1><p class="muted">{{ __('Accounts are retained in trash and can be restored without losing linked records.') }}</p></div><a href="{{ route('admin.users.index') }}" class="btn secondary">{{ __('Back to users') }}</a></div>
<div class="panel">
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;min-width:650px;"><thead><tr style="text-align:left;border-bottom:1px solid var(--border);"><th style="padding:12px;">{{ __('Name') }}</th><th style="padding:12px;">{{ __('Email') }}</th><th style="padding:12px;">{{ __('Role') }}</th><th style="padding:12px;">{{ __('Branch') }}</th><th style="padding:12px;">{{ __('Deleted at') }}</th><th style="padding:12px;">{{ __('Action') }}</th></tr></thead><tbody>
@forelse($users as $user)
<tr style="border-bottom:1px solid var(--border);"><td style="padding:12px;">{{ $user->name }}</td><td style="padding:12px;">{{ $user->email }}</td><td style="padding:12px;">{{ ucwords(str_replace('_',' ',$user->role)) }}</td><td style="padding:12px;">{{ $user->branch?->name ?? '—' }}</td><td style="padding:12px;">{{ $user->deleted_at?->format('M j, Y H:i') }}</td><td style="padding:12px;"><form method="POST" action="{{ route('admin.users.restore',$user->id) }}">@csrf<button class="btn primary" type="submit">{{ __('Restore account') }}</button></form></td></tr>
@empty
<tr><td colspan="6" class="muted" style="padding:18px;">{{ __('Trash is empty.') }}</td></tr>
@endforelse
</tbody></table></div>
<div style="margin-top:18px;">{{ $users->links() }}</div>
</div>
@endsection
