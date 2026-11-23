@extends('layouts.app')

@section('title', __('Branch Panel') . ' | ' . $branch->name)

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Branch Panel') }}</span>
        <h1 style="margin:10px 0 0">{{ $branch->name }}</h1>
        <p class="muted">{{ __('A branch-scoped workspace for your assigned center.') }}</p>
    </div>
    <span class="badge success">{{ __('Active branch') }}</span>
</div>

<section class="dash-summary" aria-label="{{ __('Branch overview') }}">
    <div class="summary-box"><span class="muted">{{ __('Total batches') }}</span><strong>{{ number_format($stats['batches']) }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Open batches') }}</span><strong>{{ number_format($stats['open_batches']) }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Branch students') }}</span><strong>{{ number_format($stats['students']) }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Branch teachers') }}</span><strong>{{ number_format($stats['teachers']) }}</strong></div>
    <div class="summary-box"><span class="muted">{{ __('Active enrollments') }}</span><strong>{{ number_format($stats['active_enrollments']) }}</strong></div>
</section>

<section class="panel" style="margin:20px 0">
    <h2>{{ __('Branch operations') }}</h2>
    <p class="muted">{{ __('Every list and action is restricted to your assigned branch. Central administration remains separate.') }}</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if(auth()->user()->hasAdminPermission('branch.batches'))
            <a class="btn primary" href="{{ route('branch.batches.index') }}">{{ __('Manage batches') }}</a>
        @endif
        @if(auth()->user()->hasAdminPermission('branch.students'))
            <a class="btn secondary" href="{{ route('branch.students.index') }}">{{ __('View students') }}</a>
        @endif
    </div>
</section>

<section class="panel">
    <h2>{{ __('Recent branch batches') }}</h2>
    @if($recentBatches->isEmpty())
        <p class="muted">{{ __('No batches have been created for this branch yet.') }}</p>
    @else
        <div class="table-responsive">
            <table>
                <thead><tr><th>{{ __('Batch') }}</th><th>{{ __('Course') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Students') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($recentBatches as $batch)
                    <tr>
                        <td>{{ $batch->title }}</td>
                        <td>{{ $batch->course?->title ?? '—' }}</td>
                        <td>{{ $batch->teacher?->name ?? '—' }}</td>
                        <td>{{ number_format($batch->enrollments_count) }}</td>
                        <td>{{ __($batch->status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
