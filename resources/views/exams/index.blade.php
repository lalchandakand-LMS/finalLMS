@extends('layouts.app')

@section('title', __('My exams') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">Student portal</span><h1 style="margin:12px 0 0;">{{ __('My exams') }}</h1><p class="muted">{{ __('Examinations for your active batches.') }}</p></div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Dashboard</a>
    </div>
    <div class="course-list">
        @forelse($exams as $exam)
            <article class="course-item">
                <span class="badge {{ now()->between($exam->starts_at, $exam->ends_at) ? 'success' : 'info' }}">
                    {{ now()->lt($exam->starts_at) ? __('Upcoming') : (now()->gt($exam->ends_at) ? __('Closed') : __('Available')) }}
                </span>
                <h2 style="margin:12px 0 6px;">{{ $exam->title }}</h2>
                <p class="muted">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</p>
                <p class="muted">{{ $exam->starts_at->format('M j, Y H:i') }} – {{ $exam->ends_at->format('M j, Y H:i') }} · {{ $exam->duration_minutes }} minutes</p>
                <a class="btn primary" href="{{ route('student.exams.show', $exam) }}">{{ __('View exam') }}</a>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No exams have been published for your active batches yet.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $exams->links() }}</div>
@endsection
