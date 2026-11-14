@extends('layouts.app')
@section('title', __('Quiz submissions') . ' | ' . $questionSet->title)
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Teacher review') }}</span><h1>{{ __('Quiz submissions') }}</h1><p class="muted">{{ $course->title }} · {{ $questionSet->title }}</p></div>
    <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a>
</div>
<div class="panel">
    @forelse($attempts as $attempt)
        <div class="course-item" style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;">
            <div><strong>{{ $attempt->student?->name ?? __('Student') }}</strong><div class="muted">{{ __('Attempt') }} #{{ $attempt->attempt_number }} · {{ $attempt->submitted_at?->format('d M Y, h:i A') }} · {{ $attempt->questions_count }} {{ __('questions') }}</div></div>
            <a class="btn primary" href="{{ route('question-sets.review.show', [$course, $questionSet, $attempt]) }}">{{ __('Review answers') }}</a>
        </div>
    @empty
        <p class="muted">{{ __('No quiz submissions are waiting for review.') }}</p>
    @endforelse
    {{ $attempts->links() }}
</div>
@endsection
