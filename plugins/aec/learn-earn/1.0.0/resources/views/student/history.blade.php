@extends('layouts.app')

@section('title', __('Exam history') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Student portal') }}</span><h1>{{ __('Exam attempt history') }}</h1><p class="muted">{{ __('Review your submitted and expired attempts.') }}</p></div>
    <a class="btn secondary" href="{{ route('student.learn-earn.exams.index') }}">{{ __('Available exams') }}</a>
</div>
<div class="panel">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>{{ __('Exam') }}</th><th>{{ __('Attempt') }}</th><th>{{ __('Status') }}</th><th>{{ __('Score') }}</th><th>{{ __('Submitted') }}</th><th>{{ __('Action') }}</th></tr></thead>
            <tbody>
            @forelse($attempts as $attempt)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:12px;">{{ $attempt->exam?->title ?? __('Exam unavailable') }}</td>
                    <td>#{{ $attempt->attempt_number }}</td>
                    <td>{{ __(ucfirst($attempt->status)) }}</td>
                    <td>{{ ($attempt->exam?->show_results || $attempt->exam?->ends_at?->lt(now())) && $attempt->status !== 'in_progress' ? $attempt->score.' / '.$attempt->max_score : '—' }}</td>
                    <td>{{ $attempt->submitted_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>
                        @if($attempt->status === 'in_progress')
                            <a class="btn secondary" href="{{ route('student.learn-earn.attempts.show', $attempt) }}">{{ __('Resume') }}</a>
                        @elseif($attempt->exam)
                            <a class="btn secondary" href="{{ route('student.learn-earn.attempts.result', $attempt) }}">{{ __('View result') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No exam attempts yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $attempts->links() }}
</div>
@endsection
