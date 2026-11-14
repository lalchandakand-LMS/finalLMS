@extends('layouts.app')

@section('title', __('Review submissions') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin control') }}</span><h1 style="margin:12px 0 0;">{{ __('Review submissions') }}</h1><p class="muted">{{ $contest->title }}</p></div>
    <a class="btn secondary" href="{{ route('admin.contests.index') }}">{{ __('Back to contests') }}</a>
</div>

<div class="panel" style="margin-bottom:18px;">
    <strong>{{ __('Scoring policy') }}</strong>
    <p class="muted" style="margin-bottom:0;">{{ __('Entries are ranked by score, then submission time, then entry ID. Review is read-only; published scores and answers are not changed here.') }}</p>
</div>

@forelse($entries as $entry)
    <section class="panel" style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div><h2 style="margin:0;">{{ $entry->user->name }}</h2><small class="muted">{{ $entry->user->email }} · {{ $entry->submitted_at->format('d M Y, h:i A') }}</small></div>
            <strong>{{ __('Score:') }} {{ $entry->score }} / {{ $contest->questions->count() }}</strong>
        </div>
        @foreach($contest->questions as $question)
            @php($selectedOption = $entry->answers[$question->id] ?? null)
            <div style="padding:12px 0;border-top:1px solid var(--border);margin-top:12px;">
                <strong>{{ $loop->iteration }}. {{ $question->question }}</strong>
                <p style="margin:6px 0 0;"><strong>{{ __('Submitted:') }}</strong> {{ $question->options[$selectedOption] ?? __('Unavailable') }}</p>
                <p style="margin:4px 0 0;"><strong>{{ __('Correct:') }}</strong> {{ $question->options[$question->correct_option] }}</p>
            </div>
        @endforeach
    </section>
@empty
    <div class="panel">{{ __('No submissions yet.') }}</div>
@endforelse

<div>{{ $entries->links() }}</div>
@endsection
