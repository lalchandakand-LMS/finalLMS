@extends('layouts.app')

@section('title', __('Attendance reports') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Attendance') }}</span><h1 style="margin:12px 0 0">{{ __('Attendance reports') }}</h1><p class="muted">{{ __('Review attendance by date, batch, status or student.') }}</p></div>
    <a class="btn secondary" href="{{ route(str_replace('.index', '.export', request()->route()->getName()), request()->query()) }}">{{ __('Export CSV') }}</a>
</div>
<form method="GET" class="panel grid grid-2" style="margin-bottom:20px">
    <div><label for="from">{{ __('From date') }}</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label for="to">{{ __('To date') }}</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div><label for="batch_id">{{ __('Batch') }}</label><select id="batch_id" name="batch_id"><option value="">{{ __('All batches') }}</option>@foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(($filters['batch_id'] ?? '') == $batch->id)>{{ $batch->title }}</option>@endforeach</select></div>
    <div><label for="status">{{ __('Status') }}</label><select id="status" name="status"><option value="">{{ __('All statuses') }}</option>@foreach(['present','late','absent','excused'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
    <div><label for="q">{{ __('Search student') }}</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('Name or email') }}"></div>
    <div style="display:flex;align-items:end;gap:8px"><button class="btn primary" type="submit">{{ __('Apply filters') }}</button><a class="btn secondary" href="{{ route(request()->route()->getName()) }}">{{ __('Reset') }}</a></div>
</form>
<div class="grid grid-2" style="margin-bottom:20px">
    @foreach(['total'=>'Total records','present'=>'Present','late'=>'Late','absent'=>'Absent','excused'=>'Excused'] as $key=>$label)
    <div class="panel"><div class="muted">{{ __($label) }}</div><strong style="font-size:1.5rem">{{ number_format($summary[$key]) }}</strong></div>
    @endforeach
</div>
<div class="panel" style="overflow-x:auto">
<table class="table">
<thead><tr><th>{{ __('Date') }}</th><th>{{ __('Student') }}</th><th>{{ __('Course / Batch') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Status') }}</th></tr></thead>
<tbody>
@forelse($records as $record)
<tr><td>{{ $record->attendance_date?->format('d M Y') }}</td><td>{{ $record->student?->name }}<div class="muted">{{ $record->student?->email }}</div></td><td>{{ $record->batch?->course?->title }}<div class="muted">{{ $record->batch?->title }}</div></td><td>{{ $record->batch?->branch?->name ?? '—' }}</td><td><span class="badge {{ $record->status === 'present' ? 'success' : ($record->status === 'absent' ? 'danger' : 'warning') }}">{{ __(ucfirst($record->status)) }}</span></td></tr>
@empty<tr><td colspan="5" class="muted">{{ __('No attendance records match these filters.') }}</td></tr>@endforelse
</tbody></table>
{{ $records->links() }}
</div>
@endsection
