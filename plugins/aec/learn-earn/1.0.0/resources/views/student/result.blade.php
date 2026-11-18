@extends('layouts.app')

@section('title', __('Exam result') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Exam result') }}</span><h1>{{ $attempt->exam->title }}</h1><p class="muted">{{ __('Attempt') }} #{{ $attempt->attempt_number }} · {{ __(ucfirst($attempt->status)) }}</p></div>
    <a class="btn secondary" href="{{ route('student.learn-earn.history') }}">{{ __('Attempt history') }}</a>
</div>
<div class="panel">
    @if($canViewScore)
        <div class="grid grid-3">
            <div><small class="muted">{{ __('Your score') }}</small><h2>{{ $attempt->score }} / {{ $attempt->max_score }}</h2></div>
            <div><small class="muted">{{ __('Pass marks') }}</small><h2>{{ $attempt->exam->pass_marks }}</h2></div>
            <div><small class="muted">{{ __('Result') }}</small><h2>{{ $attempt->score >= $attempt->exam->pass_marks ? __('Passed') : __('Not passed') }}</h2></div>
        </div>
    @else
        <p>{{ __('Your submission has been recorded. The academy will publish the result after the exam window closes.') }}</p>
    @endif
    <p class="muted">{{ __('Submitted') }}: {{ $attempt->submitted_at?->format('d M Y, h:i A') }}</p>
    <a class="btn primary" href="{{ route('student.learn-earn.exams.index') }}">{{ __('Back to exams') }}</a>
</div>
@endsection
