@extends('layouts.app')
@section('title', __('Course engagement analytics'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course engagement analytics') }}</h1>
    <p class="muted">{{ __('Monitor course engagement, lesson completion and learners at risk of dropping out.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.analytics.index') }}">{{ __('Site analytics') }}</a>
</div>
<div class="panel">
    <p class="muted">{{ __('Dropout risk means an active enrollee with no recorded lesson activity in the last 30 days, or no activity after an enrolment older than 30 days. This is an operational signal, not a confirmed withdrawal.') }}</p>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
        <thead><tr>
            <th>{{ __('Course') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Enrolled') }}</th>
            <th>{{ __('Engaged') }}</th><th>{{ __('Avg. progress') }}</th><th>{{ __('Completion rate') }}</th>
            <th>{{ __('Dropout risk') }}</th><th>{{ __('Last activity') }}</th>
        </tr></thead>
        <tbody>
        @forelse($courses as $course)
            @php($metrics = $course->engagement_metrics)
            <tr style="border-top:1px solid var(--border);">
                <td style="padding:12px;"><strong>{{ $course->title }}</strong><div class="muted">{{ $course->lesson_count }} {{ __('lessons') }}</div></td>
                <td>{{ $course->teacher?->name ?: '—' }}</td>
                <td>{{ $metrics['total_enrollments'] }}</td>
                <td>{{ $metrics['engaged_students'] }}</td>
                <td>{{ number_format($metrics['average_progress'], 1) }}%</td>
                <td>{{ number_format($metrics['completion_rate'], 1) }}%</td>
                <td><span class="badge {{ $metrics['dropout_risk_count'] > 0 ? 'warning' : 'success' }}" data-dropout-risk="{{ $metrics['dropout_risk_count'] }}">{{ $metrics['dropout_risk_count'] }}</span></td>
                <td>{{ $metrics['last_activity'] ? \Illuminate\Support\Carbon::parse($metrics['last_activity'])->diffForHumans() : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted" style="padding:18px;">{{ __('No courses found.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $courses->links() }}
</div>
@endsection
