@extends('layouts.app')

@section('title', __('Quiz attempt') . ' | ' . $questionSet->title)

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Course quiz') }}</span>
            <h1 style="margin:10px 0 0;">{{ $questionSet->title }}</h1>
            <p class="muted">{{ $course->title }} · {{ __('Attempt') }} #{{ $attempt->attempt_number }}</p>
        </div>
        <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a>
    </div>

    @if(session('success'))
        <div class="alert success" role="status">{{ session('success') }}</div>
    @endif
    @if($attempt->status === 'in_progress')
        <div class="panel" style="margin-bottom:18px;">
            <div class="meta-row">
                <strong>{{ __('Total marks:') }} {{ $attempt->max_score }}</strong>
                @if($attempt->deadline_at)
                    <span class="badge warning">{{ __('Deadline:') }} {{ $attempt->deadline_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                @endif
            </div>
            <p class="muted">{{ __('Your answers are submitted once. Review them before pressing Submit quiz.') }}</p>
        </div>

        <form method="POST" action="{{ route('student.question-sets.submit', [$course, $questionSet, $attempt]) }}" class="panel">
            @csrf
            @foreach($questions as $index => $question)
                <fieldset style="border:1px solid var(--border);border-radius:12px;padding:16px;margin:0 0 16px;">
                    <legend style="padding:0 8px;font-weight:800;">{{ $index + 1 }}. {{ $question['question'] }}</legend>
                    <p class="muted">{{ __('Marks:') }} {{ $question['marks'] }}</p>
                    @if(in_array($question['type'], ['mcq', 'true_false'], true))
                        @foreach($question['options'] ?? [] as $optionIndex => $option)
                            <label style="display:flex;align-items:center;gap:10px;margin:10px 0;">
                                <input type="radio" name="answers[{{ $question['id'] }}][selected_option]" value="{{ $optionIndex }}" @checked((string) old('answers.'.$question['id'].'.selected_option', '') === (string) $optionIndex)>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    @else
                        <label for="answer-{{ $question['id'] }}">{{ __('Your answer') }}</label>
                        <textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}][answer_text]" rows="3" maxlength="5000">{{ old('answers.'.$question['id'].'.answer_text') }}</textarea>
                    @endif
                    @error('answers.'.$question['id'].'.selected_option')<p class="error">{{ $message }}</p>@enderror
                    @error('answers.'.$question['id'].'.answer_text')<p class="error">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
            @error('answers')<p class="error">{{ $message }}</p>@enderror
            <button type="submit" class="btn primary">{{ __('Submit quiz') }}</button>
        </form>
    @else
        <section class="panel">
            @if($attempt->status === 'awaiting_manual')
                <span class="badge warning">{{ __('Awaiting teacher review') }}</span>
                <h2>{{ __('Your answers have been submitted') }}</h2>
                <p>{{ __('Objective questions have been checked. Your final result will appear after the written answers are reviewed.') }}</p>
                <p>{{ __('Objective score:') }} <strong>{{ $attempt->auto_score }} / {{ $attempt->max_score }}</strong></p>
            @else
                <span class="badge success">{{ __('Submitted') }}</span>
                <h2>{{ __('Quiz result') }}</h2>
                <p>{{ __('Score:') }} <strong>{{ $attempt->final_score ?? $attempt->auto_score }} / {{ $attempt->max_score }}</strong></p>
            @endif
            <p class="muted">{{ __('Submitted at:') }} {{ $attempt->submitted_at?->format('M j, Y g:i A') }}</p>
            <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Return to course') }}</a>
        </section>
    @endif
@endsection
