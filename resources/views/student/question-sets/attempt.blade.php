@extends('layouts.app')
@section('title', __('Quiz attempt') . ' | ' . $questionSet->title)
@section('content')
<div class="page-header">
    <div><span class="badge warning">{{ __('In progress') }}</span><h1>{{ $questionSet->title }}</h1><p class="muted">{{ $course->title }} · {{ __('Attempt') }} #{{ $attempt->attempt_number }}</p></div>
    <div style="text-align:right;"><strong>{{ __('Time remaining') }}</strong><div id="quiz-countdown" class="badge info" style="font-size:1.1rem;">{{ $attempt->deadline_at ? '—:—' : __('No limit') }}</div></div>
</div>
<form method="POST" action="{{ route('student.question-sets.attempts.submit', [$course, $questionSet, $attempt]) }}" id="quiz-attempt-form">
    @csrf
    @foreach($attempt->questions as $index => $question)
        <section class="panel" style="margin-bottom:16px;">
            <div class="meta-row"><strong>{{ __('Question') }} {{ $index + 1 }}</strong><span class="badge info">{{ $question->marks }} {{ __('marks') }}</span></div>
            <h3>{{ $question->question }}</h3>
            @if(in_array($question->type, ['mcq', 'true_false'], true))
                @foreach($question->options ?? [] as $optionIndex => $option)
                    <label style="display:flex;align-items:center;gap:10px;padding:10px;border:1px solid var(--border);border-radius:9px;margin:8px 0;">
                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $optionIndex }}" style="width:auto;">
                        <span>{{ $option }}</span>
                    </label>
                @endforeach
            @else
                <label>{{ __('Your answer') }}<textarea name="answers[{{ $question->id }}]" rows="4" maxlength="5000" placeholder="{{ __('Write your answer here...') }}"></textarea></label>
                <small class="muted">{{ __('This answer will be reviewed by a teacher.') }}</small>
            @endif
        </section>
    @endforeach
    <div class="panel"><p class="muted">{{ __('Once submitted, your answers cannot be changed. Short answers may require teacher review.') }}</p><button class="btn primary" type="submit">{{ __('Submit quiz') }}</button></div>
</form>
@if($attempt->deadline_at)
<script>
(() => {
    const deadline = new Date(@json($attempt->deadline_at->toIso8601String())).getTime();
    const display = document.getElementById('quiz-countdown');
    const form = document.getElementById('quiz-attempt-form');
    const tick = () => {
        const remaining = Math.max(0, deadline - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        display.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        if (remaining <= 0) form.requestSubmit();
    };
    tick();
    window.setInterval(tick, 1000);
})();
</script>
@endif
@endsection
