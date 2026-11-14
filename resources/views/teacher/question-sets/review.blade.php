@extends('layouts.app')
@section('title', __('Review quiz attempt'))
@section('content')
<div class="page-header">
    <div><span class="badge warning">{{ __('Pending review') }}</span><h1>{{ __('Review quiz attempt') }}</h1><p class="muted">{{ $course->title }} · {{ $questionSet->title }} · {{ $attempt->student?->name }}</p></div>
    <a class="btn secondary" href="{{ route('question-sets.review.index', [$course, $questionSet]) }}">{{ __('Back to submissions') }}</a>
</div>
<form method="POST" action="{{ route('question-sets.review.grade', [$course, $questionSet, $attempt]) }}">
    @csrf
    @foreach($attempt->questions as $index => $question)
        <section class="panel" style="margin-bottom:16px;">
            <div class="meta-row"><strong>{{ __('Question') }} {{ $index + 1 }}</strong><span class="badge info">{{ $question->marks }} {{ __('maximum marks') }}</span></div>
            <h3>{{ $question->question }}</h3>
            <p><strong>{{ __('Student answer') }}:</strong> {{ $question->answer_text ?: __('Not answered') }}</p>
            <p class="muted">{{ __('Expected answer') }}: {{ $question->correct_answer ?: __('No model answer provided') }}</p>
            <label>{{ __('Marks awarded') }}<input type="number" name="scores[{{ $question->id }}]" min="0" max="{{ $question->marks }}" value="{{ old('scores.'.$question->id, 0) }}" required></label>
            <label>{{ __('Feedback for this answer') }}<textarea name="question_feedback[{{ $question->id }}]" rows="2" maxlength="2000">{{ old('question_feedback.'.$question->id) }}</textarea></label>
        </section>
    @endforeach
    <div class="panel">
        <label>{{ __('Overall teacher feedback') }}<textarea name="feedback" rows="3" maxlength="5000">{{ old('feedback') }}</textarea></label>
        <p class="muted">{{ __('Review all short answers. The final score combines the automatic score and marks awarded here.') }}</p>
        <button class="btn primary" type="submit">{{ __('Save grading and publish result') }}</button>
    </div>
</form>
@endsection
