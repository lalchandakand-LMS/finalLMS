@extends('layouts.app')

@section('title', __('My Courses') . ' | Alom’s English Center')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Student portal') }}</span>
            <h1 style="margin:10px 0 0;">{{ __('My Courses') }}</h1>
            <p class="muted">{{ __('Continue learning and track your progress across enrolled courses.') }}</p>
        </div>
    </div>

    @if($enrollments->isEmpty())
        <section class="panel">
            <h2>{{ __('No active courses yet') }}</h2>
            <p class="muted">{{ __('Browse the course catalog and enroll to start learning.') }}</p>
            <a href="{{ route('courses.index') }}" class="btn primary">{{ __('Browse courses') }}</a>
        </section>
    @else
        <div class="grid grid-2">
            @foreach($enrollments as $enrollment)
                @php($course = $enrollment->course)
                <article class="panel">
                    <div class="meta-row" style="justify-content:space-between;gap:12px;">
                        <span class="badge {{ $enrollment->status === 'completed' ? 'success' : 'info' }}">
                            {{ ucfirst($enrollment->status) }}
                        </span>
                        <span class="muted">{{ $enrollment->completed_lessons_count }} / {{ $enrollment->total_lessons_count }} {{ __('lessons completed') }}</span>
                    </div>
                    <h2 style="margin:14px 0 6px;">{{ $course->title }}</h2>
                    <p class="muted">{{ __('Instructor') }}: {{ $course->teacher?->name ?? '—' }}</p>
                    <p>{{ \Illuminate\Support\Str::limit($course->description, 160) }}</p>

                    <div style="height:10px;background:#e8eef8;border-radius:999px;overflow:hidden;margin:16px 0 8px;">
                        <div role="progressbar" aria-label="{{ __('Course progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $enrollment->progress_percent }}" style="height:100%;width:{{ $enrollment->progress_percent }}%;background:#1769e8;border-radius:999px;"></div>
                    </div>
                    <div class="meta-row" style="justify-content:space-between;">
                        <small class="muted">{{ $enrollment->progress_percent }}% {{ __('complete') }}</small>
                        @if($enrollment->status === 'active' && $enrollment->progress_percent >= 100)
                            <form method="POST" action="{{ route('courses.complete', $course) }}">
                                @csrf
                                <button type="submit" class="btn primary">{{ __('Mark as complete') }}</button>
                            </form>
                        @elseif($enrollment->resume_content_item)
                            <a class="btn primary" href="{{ route('courses.content.show', [$course, $enrollment->resume_content_item->post]) }}">{{ $enrollment->status === 'completed' ? __('Review last lesson') : __('Resume learning') }}</a>
                        @elseif($enrollment->resume_lesson)
                            <a class="btn primary" href="{{ route('courses.lessons.show', [$course, $enrollment->resume_lesson]) }}">{{ $enrollment->status === 'completed' ? __('Review last lesson') : __('Resume learning') }}</a>
                        @else
                            <a class="btn primary" href="{{ route('courses.show', $course) }}">{{ $enrollment->status === 'completed' ? __('Review course') : __('Continue learning') }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
