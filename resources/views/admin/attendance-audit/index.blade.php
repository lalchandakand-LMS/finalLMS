@extends('layouts.app')

@section('title', __('Attendance audit history'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge">{{ __('Attendance integrity') }}</span>
        <h1>{{ __('Attendance audit history') }}</h1>
        <p class="muted">{{ __('Search and export immutable attendance changes, including offline sync, QR check-ins and reviewed corrections.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.attendance-audit.export', request()->query()) }}">{{ __('Export CSV') }}</a>
</div>

<div class="panel" style="padding:20px;margin-bottom:18px;">
    <form method="GET" action="{{ route('admin.attendance-audit.index') }}" class="grid grid-3" style="gap:12px;">
        <label>{{ __('From') }}<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>{{ __('To') }}<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>{{ __('Source') }}
            <select name="source">
                <option value="">{{ __('All sources') }}</option>
                @foreach(['web', 'offline_sync', 'offline_conflict_resolution', 'stale_location_manual_review', 'qr'] as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ __(ucwords(str_replace('_', ' ', $source))) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('Student ID') }}<input type="number" min="1" name="student_id" value="{{ $filters['student_id'] ?? '' }}"></label>
        <label>{{ __('Actor ID') }}<input type="number" min="1" name="actor_id" value="{{ $filters['actor_id'] ?? '' }}"></label>
        <label>{{ __('Batch ID') }}<input type="number" min="1" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}"></label>
        <label style="grid-column:1/-1;">{{ __('Search student, actor or reason') }}<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
        <div style="display:flex;gap:8px;align-items:end;">
            <button class="btn primary" type="submit">{{ __('Apply filters') }}</button>
            <a class="btn secondary" href="{{ route('admin.attendance-audit.index') }}">{{ __('Reset') }}</a>
        </div>
    </form>
</div>

<div class="panel" style="padding:20px;">
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>{{ __('Time') }}</th><th>{{ __('Student') }}</th><th>{{ __('Batch') }}</th>
                <th>{{ __('Actor') }}</th><th>{{ __('Change') }}</th><th>{{ __('Source') }}</th><th>{{ __('Reason') }}</th>
            </tr></thead>
            <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at }}</td>
                    <td>{{ $entry->student_name }}<br><small>{{ $entry->student_email }}</small></td>
                    <td>{{ $entry->batch_title ?? '—' }}<br><small>{{ $entry->attendance_date }}</small></td>
                    <td>{{ $entry->actor_name ?? '—' }}</td>
                    <td>{{ $entry->previous_status ? __(ucfirst($entry->previous_status)) : '—' }} → <strong>{{ __(ucfirst($entry->new_status)) }}</strong></td>
                    <td>{{ __(ucwords(str_replace('_', ' ', $entry->source))) }}</td>
                    <td>{{ $entry->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('No audit entries match these filters.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</div>
@endsection
