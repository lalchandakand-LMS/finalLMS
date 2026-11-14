@extends('layouts.app')

@section('title', $contest->title . ' | ' . config('app.name'))

@section('content')
<div class="page-header"><div><span class="badge info">Free participation</span><h1 style="margin:12px 0 0;">{{ $contest->title }}</h1><p class="muted">{{ $contest->description }}</p></div></div>
<div class="dash-summary" style="margin-bottom:20px;">
    <div class="summary-box"><span class="muted">Prize</span><strong style="font-size:1rem;">{{ $contest->prize_description }}</strong></div>
    <div class="summary-box"><span class="muted">Participants</span><strong>{{ $contest->entries_count ?? $contest->entries()->count() }}</strong></div>
    <div class="summary-box"><span class="muted">Questions</span><strong>{{ $contest->questions->count() }}</strong></div>
    <div class="summary-box"><span class="muted">Deadline</span><strong style="font-size:1rem;">{{ $contest->ends_at->format('d M, h:i A') }}</strong></div>
</div>
@if($entry)
    @php($answerReviewOpen = $contest->drawn_at !== null || now()->greaterThanOrEqualTo($contest->ends_at))
    <div class="panel" style="margin-bottom:20px;">
        <span class="badge success">{{ __('Submitted') }}</span>
        <h2>{{ __('Thank you for participating!') }}</h2>
        <p>{{ __('Your answers were received on') }} {{ $entry->submitted_at->format('d M Y, h:i A') }}.</p>
        @if($answerReviewOpen)
            <p><strong>{{ __('Your score:') }}</strong> {{ $entry->score }} / {{ $contest->questions->count() }}</p>
            <p class="muted">{{ __('The answer key is available because the contest has ended.') }}</p>
        @else
            <p class="muted">{{ __('Your submitted choices are shown below. Correct answers remain hidden until the contest ends.') }}</p>
        @endif
        @foreach($contest->questions as $question)
            @php($selectedOption = $entry->answers[$question->id] ?? null)
            <div style="padding:12px 0;border-top:1px solid var(--border);">
                <strong>{{ $loop->iteration }}. {{ $question->question }}</strong>
                <p style="margin:6px 0 0;"><strong>{{ __('Your answer:') }}</strong> {{ $question->options[$selectedOption] ?? __('Unavailable') }}</p>
                @if($answerReviewOpen)
                    <p style="margin:4px 0 0;"><strong>{{ __('Correct answer:') }}</strong> {{ $question->options[$question->correct_option] }}</p>
                @endif
            </div>
        @endforeach
    </div>
@elseif(now()->between($contest->starts_at, $contest->ends_at) && $contest->status === 'published')
    @auth
        <form class="panel" method="POST" action="{{ route('contests.submit', $contest) }}">
            @csrf
            <h2 style="margin-top:0;">Answer the questions</h2>
            @foreach($contest->questions as $question)
                <fieldset style="border:1px solid var(--border);border-radius:12px;padding:16px;margin:14px 0;">
                    <legend style="font-weight:700;">{{ $loop->iteration }}. {{ $question->question }}</legend>
                    @foreach($question->options as $index => $option)
                        <label style="display:flex;gap:10px;align-items:center;padding:8px 0;font-weight:500;"><input type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}" required style="width:auto;"> {{ $option }}</label>
                    @endforeach
                </fieldset>
            @endforeach
            <p class="muted">One entry per account. Participation is free; no payment or purchase is required.</p>
            <button class="btn primary" type="submit">Submit my answers</button>
        </form>
    @else
        <div class="panel"><h2>Login to participate</h2><p>Create a free account or sign in to submit your answers. Each account can enter once.</p><a class="btn primary" href="{{ route('login') }}">Login</a> <a class="btn secondary" href="{{ route('register') }}">Create account</a></div>
    @endauth
@else
    <div class="panel"><strong>{{ $contest->drawn_at ? 'This contest has ended and final results have been published.' : 'This contest is not accepting answers right now.' }}</strong></div>
@endif
@if($contest->winners->isNotEmpty())
    <div class="panel" style="margin-top:20px;"><h2>🏆 Top participants</h2><p class="muted">Final ranking uses score (highest first), then earliest submission time.</p>
        @foreach($contest->winners as $winner)
            <div style="padding:12px 0;border-bottom:1px solid var(--border);"><strong>#{{ $winner->rank }} — {{ $winner->user->name }}</strong><div class="muted">{{ $winner->prize_snapshot }}</div></div>
        @endforeach
    </div>
@endif
@endsection
