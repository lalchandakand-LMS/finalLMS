@extends('layouts.app')

@section('title', __('Branch attendance batches') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Branch workspace') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Attendance batches') }}</h1>
            <p class="muted">{{ __('Only batches in your assigned branch are listed here.') }}</p>
        </div>
        <a href="{{ route('admin.branch-workspace.attendance.reports') }}" class="btn secondary">{{ __('Attendance reports') }}</a>
    </div>

    <div class="panel">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>{{ __('Batch') }}</th><th>{{ __('Course') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td>{{ $batch->title }}</td>
                            <td>{{ $batch->course?->title ?? '—' }}</td>
                            <td>{{ __(ucfirst($batch->status)) }}</td>
                            <td><a class="btn primary" href="{{ route('admin.branch-workspace.batches.attendance.index', $batch) }}">{{ __('Take attendance') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('No batches found in your branch.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $batches->links() }}</div>
    </div>
@endsection
