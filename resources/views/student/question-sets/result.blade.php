@extends('layouts.app')
@section('title', __('Quiz result') . ' | ' . $questionSet->title)
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Quiz result') }}</span><h1>{{ $questionSet->title }}</h1><p class="muted">{{ $course->title }} · {{ __('Attempt') }} #{{ $attempt->attempt_number }}</p></div>
    <a class="btn secondary" href="{{ route('student.question-sets.show', [$course, $questionSet]) }}">{{ __('Back to quiz') }}</a>
</div>
<div class="panel" style="max-width:900px;margin-bottom:18px;">
    @if($attempt->status === 'pending_review')
        <span class="badge warning">{{ __('Pending teacher review') }}</span>
        <h2>{{ __('Your answers were submitted successfully.') }}</h2>
        <p class="muted">{{ __('Automatically graded score: :score / :total. Your final result will appear after short answers are reviewed.', ['score' => $attempt->auto_score, 'total' => $attempt->max_score]) }}</p>
    @else
        <span class="badge success">{{ __('Graded') }}</span>
        <h2>{{ __('Score: :score / :total', ['score' => $attempt->final_score, 'total' => $attempt->max_score]) }}</h2>
        <p class="muted">{{ __('Submitted :date', ['date' => $attempt->submitted_at?->format('d M Y, h:i A')]) }}</p>
    @endif
    @if($attempt->teacher_feedback)<p><strong>{{ __('Teacher feedback') }}:</strong> {{ $attempt->teacher_feedback }}</p>@endif
</div>
<div class="panel" style="max-width:900px;">
    <h2>{{ __('Answer summary') }}</h2>
    @foreach($attempt->questions as $index => $question)
        <div class="course-item" style="margin-bottom:12px;">
            <strong>{{ __('Question') }} {{ $index + 1 }}. {{ $question->question }}</strong>
            <p class="muted">{{ __('Your answer') }}:
                @if(in_array($question->type, ['mcq', 'true_false'], true))
                    {{ $question->selected_option !== null ? ($question->options[$question->selected_option] ?? __('Not answered')) : __('Not answered') }}
                @else
                    {{ $question->answer_text ?: __('Not answered') }}
                @endif
            </p>
            @if($question->type !== 'short_answer')
                <span class="badge {{ $question->is_correct ? 'success' : 'warning' }}">{{ $question->marks_awarded }} / {{ $question->marks }} {{ __('marks') }}</span>
            @elseif($question->graded_at)
                <span class="badge info">{{ $question->marks_awarded }} / {{ $question->marks }} {{ __('marks') }}</span>
                @if($question->manual_feedback)<p class="muted">{{ $question->manual_feedback }}</p>@endif
            @else
                <span class="badge warning">{{ __('Awaiting manual grading') }}</span>
            @endif
        </div>
    @endforeach
</div>
@endsection
