@extends('layouts.app')

@section('title', 'Edit Quiz Contest | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">Admin control</span><h1 style="margin:12px 0 0;">Edit quiz contest</h1><p class="muted">Editing is available only before the contest starts and before the first submission.</p></div>
    <a class="btn secondary" href="{{ route('admin.contests.index') }}">Back to contests</a>
</div>

<div class="panel" style="max-width:1000px;margin:0 auto;">
    <form method="POST" action="{{ route('admin.contests.update', $contest) }}">
        @csrf
        @method('PUT')
        <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <div><label>Contest title</label><input name="title" maxlength="180" required value="{{ old('title', $contest->title) }}"></div>
            <div><label>Prize / reward details</label><input name="prize_description" maxlength="500" required value="{{ old('prize_description', $contest->prize_description) }}"></div>
            <div><label>Number of winners</label><input type="number" name="winner_count" min="1" max="100" required value="{{ old('winner_count', $contest->winner_count) }}"></div>
            <div><label>Starts at</label><input type="datetime-local" name="starts_at" required value="{{ old('starts_at', $contest->starts_at->format('Y-m-d\\TH:i')) }}"></div>
            <div><label>Ends at</label><input type="datetime-local" name="ends_at" required value="{{ old('ends_at', $contest->ends_at->format('Y-m-d\\TH:i')) }}"></div>
            <div style="grid-column:1/-1;"><label>Description / rules</label><textarea name="description" rows="3" maxlength="5000">{{ old('description', $contest->description) }}</textarea></div>
        </div>
        <h2>Questions</h2>
        <p class="muted">Replace or revise questions here. Existing submissions lock this editor.</p>
        @foreach($contest->questions as $index => $question)
            <section class="panel" style="padding:15px;margin:12px 0;">
                <strong>Question {{ $index + 1 }}</strong>
                <label>Question text</label>
                <input name="questions[{{ $index }}][question]" required maxlength="2000" value="{{ old("questions.$index.question", $question->question) }}">
                <div class="grid grid-2">
                    @foreach($question->options as $optionIndex => $option)
                        <div><label>Option {{ ['A','B','C','D'][$optionIndex] }}</label><input name="questions[{{ $index }}][options][]" required maxlength="300" value="{{ old("questions.$index.options.$optionIndex", $option) }}"></div>
                    @endforeach
                </div>
                <label>Correct answer</label>
                <select name="questions[{{ $index }}][correct_option]" required>
                    @foreach(['A','B','C','D'] as $optionIndex => $letter)
                        <option value="{{ $optionIndex }}" @selected((int) old("questions.$index.correct_option", $question->correct_option) === $optionIndex)>{{ $letter }}</option>
                    @endforeach
                </select>
            </section>
        @endforeach
        <button class="btn primary" type="submit">Save contest changes</button>
    </form>
</div>
@endsection
