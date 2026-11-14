@extends('layouts.app')

@section('title', __('Quiz submissions') . ' | ' . $questionSet->title)

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Teacher workspace') }}</span>
            <h1 style="margin:10px 0 0;">{{ __('Quiz submissions') }}</h1>
            <p class="muted">{{ $course->title }} · {{ $questionSet->title }}</p>
        </div>
        <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a>
    </div>

    <section class="panel">
        <div class="meta-row">
            <div><h2 style="margin:0;">{{ __('Written answer review') }}</h2><p class="muted">{{ __('Review written responses, award marks and publish the final score.') }}</p></div>
            <span class="badge info">{{ $attempts->total() }} {{ __('submissions') }}</span>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th>{{ __('Student') }}</th>
                    <th>{{ __('Attempt') }}</th>
                    <th>{{ __('Submitted') }}</th>
                    <th>{{ __('Written questions') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Score') }}</th>
                    <th>{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($attempts as $attempt)
                    <tr style="border-top:1px solid var(--border);">
                        <td><strong>{{ $attempt->student?->name ?? __('Student account unavailable') }}</strong><div class="muted">{{ $attempt->student?->email }}</div></td>
                        <td>#{{ $attempt->attempt_number }}</td>
                        <td>{{ $attempt->submitted_at?->format('M j, Y g:i A') }}</td>
                        <td>{{ $attempt->written_questions_count }}</td>
                        <td>
                            @if($attempt->status === 'awaiting_manual')
                                <span class="badge warning">{{ __('Awaiting review') }}</span>
                            @else
                                <span class="badge success">{{ __('Graded') }}</span>
                            @endif
                        </td>
                        <td>{{ $attempt->final_score ?? $attempt->auto_score }} / {{ $attempt->max_score }}</td>
                        <td><a class="btn secondary" href="{{ route('teacher.question-set-attempts.show', $attempt) }}">{{ $attempt->status === 'graded' ? __('View result') : __('Review answers') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('No submitted written answers need review yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $attempts->links() }}
    </section>
@endsection
