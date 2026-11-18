@extends('layouts.app')

@section('title', __('Learn & Earn') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Student portal') }}</span><h1>{{ __('Learn & Earn exams') }}</h1><p class="muted">{{ __('Take free exams, review your results and track your attempts.') }}</p></div>
    <a class="btn secondary" href="{{ route('student.learn-earn.history') }}">{{ __('Attempt history') }}</a>
</div>
<div class="grid grid-2">
    @forelse($exams as $exam)
        <article class="panel">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <h2 style="margin:0;">{{ $exam->title }}</h2>
                <span class="badge {{ $exam->access_type === 'free' ? 'success' : 'warning' }}">{{ $exam->access_type === 'free' ? __('Free') : __('Paid') }}</span>
            </div>
            <p class="muted">{{ \Illuminate\Support\Str::limit($exam->description, 140) }}</p>
            <div class="meta-row" style="flex-wrap:wrap;gap:10px;">
                <small>{{ __('Starts') }}: {{ $exam->starts_at->format('d M Y, h:i A') }}</small>
                <small>{{ __('Duration') }}: {{ $exam->duration_minutes }} {{ __('minutes') }}</small>
                <small>{{ __('Your attempts') }}: {{ $exam->my_attempts_count }} / {{ $exam->max_attempts }}</small>
            </div>
            <a class="btn secondary" href="{{ route('student.learn-earn.exams.show', $exam) }}">{{ __('Open exam') }}</a>
        </article>
    @empty
        <div class="panel muted">{{ __('No exams are currently available.') }}</div>
    @endforelse
</div>
<div style="margin-top:18px;">{{ $exams->links() }}</div>

<div class="panel" style="margin-top:24px;">
    <div class="page-header"><div><h2>{{ __('Recent attempts') }}</h2></div><a class="btn secondary" href="{{ route('student.learn-earn.history') }}">{{ __('View all') }}</a></div>
    @forelse($recentAttempts as $attempt)
        <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--border);">
            <span>{{ $attempt->exam?->title ?? __('Exam') }} · {{ __(ucfirst($attempt->status)) }}</span>
            @if($attempt->status !== 'in_progress')
                <a href="{{ route('student.learn-earn.attempts.result', $attempt) }}">{{ __('View result') }}</a>
            @else
                <a href="{{ route('student.learn-earn.attempts.show', $attempt) }}">{{ __('Resume') }}</a>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('You have not attempted an exam yet.') }}</p>
    @endforelse
</div>
@endsection
