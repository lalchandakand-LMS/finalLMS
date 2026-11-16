@extends('layouts.app')

@section('title', __('Course combos') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">Admin</span><h1 style="margin:12px 0 0;">{{ __('Course combos') }}</h1><p class="muted">{{ __('Group published courses into one discounted purchase.') }}</p></div>
        <a class="btn primary" href="{{ route('admin.course-combos.create') }}">{{ __('Create combo') }}</a>
    </div>
    <div class="panel" style="overflow-x:auto;">
        <table style="width:100%;min-width:760px;border-collapse:collapse;">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border);"><th style="padding:12px;">{{ __('Combo') }}</th><th>{{ __('Courses') }}</th><th>{{ __('Price') }}</th><th>{{ __('Payments') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($combos as $combo)
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px;"><strong>{{ $combo->title }}</strong><br><small class="muted">/course-combos/{{ $combo->slug }}</small></td>
                    <td>{{ $combo->courses_count }}</td>
                    <td>৳{{ number_format((float) $combo->price, 2) }}</td>
                    <td>{{ $combo->payments_count }}</td>
                    <td><span class="badge {{ $combo->status === 'published' ? 'success' : 'warning' }}">{{ __(ucfirst($combo->status)) }}</span></td>
                    <td style="white-space:nowrap;">
                        <a class="btn secondary" href="{{ route('admin.course-combos.edit', $combo) }}">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.course-combos.destroy', $combo) }}" style="display:inline;">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete / archive') }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No course combos created yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px;">{{ $combos->links() }}</div>
@endsection
