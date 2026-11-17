@extends('layouts.app')

@section('title', $exam->title . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Student exam') }}</span><h1>{{ $exam->title }}</h1><p class="muted">{{ $exam->description }}</p></div>
    <a class="btn secondary" href="{{ route('student.learn-earn.exams.index') }}">{{ __('Back to exams') }}</a>
</div>
<div class="panel">
    <div class="grid grid-3">
        <div><small class="muted">{{ __('Duration') }}</small><h3>{{ $exam->duration_minutes }} {{ __('minutes') }}</h3></div>
        <div><small class="muted">{{ __('Pass marks') }}</small><h3>{{ $exam->pass_marks }}</h3></div>
        <div><small class="muted">{{ __('Attempts') }}</small><h3>{{ $attempt ? $attempt->attempt_number : 0 }} / {{ $exam->max_attempts }}</h3></div>
    </div>
    <p>{{ __('Exam time') }}: {{ $exam->starts_at->format('d M Y, h:i A') }} – {{ $exam->ends_at->format('d M Y, h:i A') }}</p>

    @if($attempt && $attempt->status === 'in_progress')
        <p class="badge success">{{ __('You have an exam in progress.') }}</p>
        <a class="btn primary" href="{{ route('student.learn-earn.attempts.show', $attempt) }}">{{ __('Resume exam') }}</a>
    @elseif($exam->access_type === 'paid')
        <p class="badge warning">{{ __('Paid exam') }}</p>
        <p class="muted">{{ __('Secure checkout and payment verification for paid exams are being prepared. This exam cannot be started until payment is verified.') }}</p>
    @elseif(now()->lt($exam->starts_at))
        <p class="badge info">{{ __('This exam has not started yet.') }}</p>
    @elseif(now()->gt($exam->ends_at))
        <p class="badge warning">{{ __('This exam has ended.') }}</p>
    @elseif($attempt && $attempt->attempt_number >= $exam->max_attempts)
        <p class="badge warning">{{ __('You have used all attempts for this exam.') }}</p>
    @else
        <form method="POST" action="{{ route('student.learn-earn.exams.start', $exam) }}">
            @csrf
            <button class="btn primary" type="submit">{{ __('Start free exam') }}</button>
        </form>
    @endif
</div>
@endsection
