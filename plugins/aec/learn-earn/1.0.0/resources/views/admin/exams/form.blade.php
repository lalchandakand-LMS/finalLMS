@extends('layouts.app')

@section('title', __('Create Learn & Earn Exam') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Learn & Earn') }}</span><h1>{{ __('Create exam') }}</h1><p class="muted">{{ __('Select published MCQs. Question text, options, answer keys and marks are copied into an immutable exam snapshot.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.learn-earn.exams.index') }}">{{ __('Back to exams') }}</a>
</div>

<div class="panel">
    <form method="POST" action="{{ route('admin.learn-earn.exams.store') }}">
        @csrf
        <div class="grid grid-2">
            <label>{{ __('Exam title') }}<input name="title" maxlength="180" required value="{{ old('title') }}"></label>
            <label>{{ __('Status') }}<select name="status"><option value="draft" @selected(old('status', 'draft') === 'draft')>{{ __('Draft') }}</option><option value="published" @selected(old('status') === 'published')>{{ __('Published') }}</option></select></label>
            <label>{{ __('Starts at') }}<input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></label>
            <label>{{ __('Ends at') }}<input type="datetime-local" name="ends_at" required value="{{ old('ends_at') }}"></label>
            <label>{{ __('Duration (minutes)') }}<input type="number" name="duration_minutes" min="1" max="600" required value="{{ old('duration_minutes', 30) }}"></label>
            <label>{{ __('Pass marks') }}<input type="number" name="pass_marks" min="0" max="100000" required value="{{ old('pass_marks', 0) }}"></label>
            <label>{{ __('Questions per attempt (optional)') }}<input type="number" name="questions_per_attempt" min="1" max="100" value="{{ old('questions_per_attempt') }}"></label>
            <label>{{ __('Maximum attempts per student') }}<input type="number" name="max_attempts" min="1" max="100" required value="{{ old('max_attempts', 1) }}"></label>
            <label>{{ __('Access type') }}<select name="access_type" id="learn-earn-access-type"><option value="free" @selected(old('access_type', 'free') === 'free')>{{ __('Free') }}</option><option value="paid" @selected(old('access_type') === 'paid')>{{ __('Paid') }}</option></select></label>
            <label>{{ __('Price (BDT; required for paid exams)') }}<input type="number" name="price" min="0" max="1000000" step="0.01" value="{{ old('price', 0) }}"></label>
        </div>
        <label>{{ __('Description / Instructions') }}<textarea name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></label>
        <div class="grid grid-2">
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="randomize_questions" value="1" @checked(old('randomize_questions', true)) style="width:auto;">{{ __('Randomize question order') }}</label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="show_results" value="1" @checked(old('show_results', true)) style="width:auto;">{{ __('Show results after submission') }}</label>
        </div>
        <input type="hidden" name="currency" value="BDT">

        <div style="margin:20px 0;">
            <h2>{{ __('Select published MCQs') }}</h2>
            <p class="muted">{{ __('Only published questions from active categories (or uncategorized questions) are available.') }}</p>
            <div style="display:grid;gap:10px;">
                @forelse($questions as $question)
                    <label class="panel" style="display:flex;gap:12px;align-items:flex-start;">
                        <input type="checkbox" name="question_ids[]" value="{{ $question->id }}" @checked(in_array((string)$question->id, old('question_ids', []), true)) style="width:auto;margin-top:5px;">
                        <span style="flex:1;">
                            <strong>{{ $question->question }}</strong>
                            <span class="muted" style="display:block;">{{ $question->category?->name ?? __('Uncategorized') }} · {{ $question->marks }} / -{{ $question->negative_marks }} · {{ ucfirst($question->difficulty) }}</span>
                            <span style="display:block;">@foreach($question->options as $index => $option){{ ['A','B','C','D'][$index] }}. {{ $option }}@if(!$loop->last) · @endif @endforeach</span>
                        </span>
                    </label>
                @empty
                    <div class="panel muted">{{ __('No published MCQs are available. Publish questions before creating an exam.') }}</div>
                @endforelse
            </div>
        </div>
        <button class="btn primary" type="submit" @disabled($questions->isEmpty())>{{ __('Create exam and snapshot selected questions') }}</button>
    </form>
</div>
@endsection
