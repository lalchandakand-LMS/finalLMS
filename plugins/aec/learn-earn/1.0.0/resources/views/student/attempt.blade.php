@extends('layouts.app')

@section('title', __('Exam attempt') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge warning">{{ __('In progress') }}</span><h1>{{ $attempt->exam->title }}</h1><p class="muted">{{ __('Attempt') }} #{{ $attempt->attempt_number }} · {{ __('Deadline') }}: {{ $attempt->ends_at->format('d M Y, h:i A') }}</p></div>
</div>
<div class="panel">
    <p class="muted">{{ __('Choose one answer for each question. Your score is calculated on the server after submission. Correct answers are not sent to your browser.') }}</p>
    <form method="POST" action="{{ route('student.learn-earn.attempts.submit', $attempt) }}">
        @csrf
        @foreach($attempt->attemptQuestions as $attemptQuestion)
            @php($question = $attemptQuestion->examQuestion)
            @if($question)
            @php($saved = $attempt->answers->firstWhere('exam_question_id', $question->id)?->selected_option)
            <fieldset class="panel" style="margin:16px 0;">
                <legend><strong>{{ $loop->iteration }}. {{ $question->question }}</strong></legend>
                <p class="muted">{{ $question->marks }} {{ __('marks') }} · -{{ $question->negative_marks }} {{ __('for wrong answer') }}</p>
                @foreach($question->options as $index => $option)
                    <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;">
                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}" @checked((string)$saved === (string)$index) style="width:auto;margin-top:4px;">
                        <span>{{ ['A','B','C','D'][$index] }}. {{ $option }}</span>
                    </label>
                @endforeach
            </fieldset>
            @endif
        @endforeach
        <button class="btn primary" type="submit" onclick="return confirm('{{ __('Submit this exam now? You cannot change answers after submission.') }}')">{{ __('Submit exam') }}</button>
    </form>
</div>
@endsection
