@extends('layouts.app')
@section('title', $questionSet->title . ' | ' . $course->title)
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Course quiz') }}</span><h1>{{ $questionSet->title }}</h1><p class="muted">{{ $course->title }}</p></div>
    <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a>
</div>
<div class="panel" style="max-width:900px;">
    @if($questionSet->description)<p>{{ $questionSet->description }}</p>@endif
    <div class="course-detail-stats">
        <div class="course-detail-stat"><strong>{{ $questionSet->questions_count }}</strong><small>{{ __('Questions') }}</small></div>
        <div class="course-detail-stat"><strong>{{ $pivot->time_limit_minutes ?: '—' }}</strong><small>{{ __('Time limit (minutes)') }}</small></div>
        <div class="course-detail-stat"><strong>{{ $pivot->max_attempts ?: __('Unlimited') }}</strong><small>{{ __('Maximum attempts') }}</small></div>
    </div>
    @if($activeAttempt)
        <div class="flash warning">{{ __('You have an unfinished attempt. Continue it before starting another attempt.') }}</div>
        <a class="btn primary" href="{{ route('student.question-sets.attempts.show', [$course, $questionSet, $activeAttempt]) }}">{{ __('Continue quiz') }}</a>
    @elseif($pivot->max_attempts && $attempts->count() >= $pivot->max_attempts)
        <div class="flash warning">{{ __('You have used all allowed attempts for this quiz.') }}</div>
    @else
        <form method="POST" action="{{ route('student.question-sets.attempts.start', [$course, $questionSet]) }}">
            @csrf
            <button class="btn primary" type="submit">{{ __('Start quiz') }}</button>
        </form>
    @endif
</div>
@if($attempts->isNotEmpty())
<div class="panel" style="margin-top:20px;max-width:900px;">
    <h2>{{ __('Your attempts') }}</h2>
    @foreach($attempts as $attempt)
        <div class="course-item" style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;">
            <div><strong>{{ __('Attempt') }} #{{ $attempt->attempt_number }}</strong><div class="muted">{{ $attempt->submitted_at?->format('d M Y, h:i A') ?: __('In progress') }} · {{ __(ucwords(str_replace('_', ' ', $attempt->status))) }}</div></div>
            @if($attempt->status === 'in_progress')
                <a class="btn secondary" href="{{ route('student.question-sets.attempts.show', [$course, $questionSet, $attempt]) }}">{{ __('Continue') }}</a>
            @elseif($attempt->status !== 'in_progress')
                <a class="btn secondary" href="{{ route('student.question-sets.attempts.result', [$course, $questionSet, $attempt]) }}">{{ __('View result') }}</a>
            @endif
        </div>
    @endforeach
</div>
@endif
@endsection
