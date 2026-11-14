@extends('layouts.app')

@section('title', __('Grade quiz attempt') . ' | ' . $attempt->questionSet->title)

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Teacher workspace') }}</span>
            <h1 style="margin:10px 0 0;">{{ __('Grade quiz attempt') }}</h1>
            <p class="muted">{{ $attempt->course->title }} · {{ $attempt->questionSet->title }} · {{ $attempt->student?->name }} · #{{ $attempt->attempt_number }}</p>
        </div>
        <a class="btn secondary" href="{{ route('teacher.question-set-attempts.index', [$attempt->course, $attempt->questionSet]) }}">{{ __('Back to submissions') }}</a>
    </div>

    <section class="panel" style="margin-bottom:18px;">
        <div class="meta-row">
            <strong>{{ __('Objective score:') }} {{ $attempt->auto_score }} / {{ $attempt->max_score }}</strong>
            @if($attempt->status === 'graded')
                <span class="badge success">{{ __('Final score:') }} {{ $attempt->final_score }} / {{ $attempt->max_score }}</span>
            @else
                <span class="badge warning">{{ __('Awaiting manual review') }}</span>
            @endif
        </div>
        <p class="muted">{{ __('Submitted:') }} {{ $attempt->submitted_at?->format('M j, Y g:i A') }}</p>
    </section>

    @if($attempt->status === 'graded')
        <section class="panel">
            <h2>{{ __('Grading summary') }}</h2>
            <p>{{ __('Manual score:') }} <strong>{{ $attempt->manual_score }}</strong></p>
            @if($attempt->teacher_feedback)
                <p><strong>{{ __('Overall feedback:') }}</strong> {{ $attempt->teacher_feedback }}</p>
            @endif
            <p class="muted">{{ __('Graded by:') }} {{ $attempt->grader?->name ?? __('Admin') }} · {{ $attempt->graded_at?->format('M j, Y g:i A') }}</p>
        </section>
    @endif

    @if($attempt->status === 'awaiting_manual')
        <form method="POST" action="{{ route('teacher.question-set-attempts.grade', $attempt) }}" class="panel">
            @csrf
    @endif

    @foreach($questions as $index => $question)
        <article class="panel" style="margin-bottom:14px;">
            <div class="meta-row">
                <h3 style="margin:0;">{{ $index + 1 }}. {{ $question->question }}</h3>
                <span class="badge info">{{ $question->marks }} {{ __('marks') }}</span>
            </div>
            @if(in_array($question->type, ['mcq', 'true_false'], true))
                <p>{{ __('Student answer:') }} <strong>{{ $question->selected_option !== null ? ($question->options[$question->selected_option] ?? __('Not answered')) : __('Not answered') }}</strong></p>
                <p>{{ __('Correct answer:') }} <strong>{{ $question->options[$question->correct_option] ?? __('Not configured') }}</strong></p>
                <p class="muted">{{ __('Auto score:') }} {{ $question->marks_awarded }} / {{ $question->marks }}</p>
            @else
                <div style="background:var(--surface-muted);padding:12px;border-radius:10px;margin:12px 0;">
                    <strong>{{ __('Student response') }}</strong>
                    <p style="white-space:pre-wrap;margin-bottom:0;">{{ $question->answer_text ?: __('No written answer submitted.') }}</p>
                </div>
                @if($question->correct_answer)
                    <p><strong>{{ __('Reference answer:') }}</strong> {{ $question->correct_answer }}</p>
                @endif
                @if($question->explanation)
                    <p class="muted"><strong>{{ __('Explanation:') }}</strong> {{ $question->explanation }}</p>
                @endif
                @if($attempt->status === 'awaiting_manual')
                    <div class="grid grid-2">
                        <label>{{ __('Marks awarded (0–:max)', ['max' => $question->marks]) }}
                            <input type="number" name="answers[{{ $question->id }}][marks_awarded]" min="0" max="{{ $question->marks }}" required value="{{ old('answers.'.$question->id.'.marks_awarded', $question->marks_awarded) }}">
                        </label>
                        <label>{{ __('Feedback for this answer') }}
                            <textarea name="answers[{{ $question->id }}][feedback]" rows="2" maxlength="1000">{{ old('answers.'.$question->id.'.feedback', $question->manual_feedback) }}</textarea>
                        </label>
                    </div>
                    @error('answers.'.$question->id.'.marks_awarded')<p class="error">{{ $message }}</p>@enderror
                @else
                    <p><strong>{{ __('Marks awarded:') }}</strong> {{ $question->marks_awarded }} / {{ $question->marks }}</p>
                    @if($question->manual_feedback)<p><strong>{{ __('Feedback:') }}</strong> {{ $question->manual_feedback }}</p>@endif
                @endif
            @endif
        </article>
    @endforeach

    @if($attempt->status === 'awaiting_manual')
            <label>{{ __('Overall teacher feedback') }}
                <textarea name="teacher_feedback" rows="3" maxlength="2000">{{ old('teacher_feedback') }}</textarea>
            </label>
            @error('answers')<p class="error">{{ $message }}</p>@enderror
            <button type="submit" class="btn primary">{{ __('Publish final result') }}</button>
        </form>
    @endif
@endsection
