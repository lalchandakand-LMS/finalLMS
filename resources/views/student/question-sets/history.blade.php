@extends('layouts.app')

@section('title', __('Your quiz attempts') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Student workspace') }}</span>
            <h1 style="margin:10px 0 0;">{{ __('Your quiz attempts') }}</h1>
            <p class="muted">{{ __('Review current quizzes, submitted answers and published results from all your courses.') }}</p>
        </div>
        <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
    </div>

    <section class="panel">
        @forelse($attempts as $attempt)
            <article class="course-item" style="margin-bottom:12px;">
                <div class="meta-row" style="align-items:flex-start;">
                    <div>
                        <h3 style="margin:0 0 6px;">{{ $attempt->questionSet?->title ?? __('Quiz unavailable') }}</h3>
                        <p class="muted" style="margin:0;">{{ $attempt->course?->title ?? __('Course unavailable') }} · {{ __('Attempt') }} #{{ $attempt->attempt_number }}</p>
                        <p class="muted" style="margin:6px 0 0;">{{ __('Started:') }} {{ $attempt->started_at?->format('M j, Y g:i A') }}</p>
                    </div>
                    <div style="text-align:right;">
                        @if($attempt->status === 'in_progress')
                            <span class="badge warning">{{ __('In progress') }}</span>
                        @elseif($attempt->status === 'awaiting_manual')
                            <span class="badge info">{{ __('Awaiting teacher review') }}</span>
                        @elseif($attempt->status === 'graded')
                            <span class="badge success">{{ __('Graded') }}</span>
                        @else
                            <span class="badge success">{{ __('Submitted') }}</span>
                        @endif
                        <p style="margin:8px 0 0;"><strong>
                            @if($attempt->final_score !== null)
                                {{ $attempt->final_score }} / {{ $attempt->max_score }}
                            @elseif($attempt->status === 'awaiting_manual')
                                {{ __('Objective score:') }} {{ $attempt->auto_score }} / {{ $attempt->max_score }}
                            @elseif($attempt->status === 'in_progress')
                                {{ __('Not submitted') }}
                            @else
                                {{ $attempt->auto_score }} / {{ $attempt->max_score }}
                            @endif
                        </strong></p>
                    </div>
                </div>
                <div class="meta-row" style="margin-top:12px;">
                    <span class="muted">{{ __('Submitted:') }} {{ $attempt->submitted_at?->format('M j, Y g:i A') ?? '—' }}</span>
                    <a class="btn secondary" href="{{ route('student.question-sets.show', [$attempt->course, $attempt->questionSet, $attempt]) }}">
                        {{ $attempt->status === 'in_progress' ? __('Continue quiz') : __('View attempt') }}
                    </a>
                </div>
            </article>
        @empty
            <div style="text-align:center;padding:34px 16px;">
                <h2>{{ __('No quiz attempts yet') }}</h2>
                <p class="muted">{{ __('Quizzes attached to your enrolled courses will appear here after you start one.') }}</p>
                <a class="btn primary" href="{{ route('student.courses.index') }}">{{ __('My Courses & Progress') }}</a>
            </div>
        @endforelse
        {{ $attempts->links() }}
    </section>
@endsection
