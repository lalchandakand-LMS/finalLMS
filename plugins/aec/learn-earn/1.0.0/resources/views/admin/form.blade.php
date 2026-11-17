@extends('layouts.app')

@section('title', ($question->exists ? __('Edit MCQ') : __('Create MCQ')) . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Learn & Earn') }}</span><h1>{{ $question->exists ? __('Edit MCQ') : __('Create MCQ') }}</h1><p class="muted">{{ __('Create and organize reusable MCQs for future Learn & Earn exams.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.learn-earn.questions.index') }}">{{ __('Back to question bank') }}</a>
</div>

<div class="panel">
    <form method="POST" action="{{ $question->exists ? route('admin.learn-earn.questions.update', $question) : route('admin.learn-earn.questions.store') }}">
        @csrf
        @if($question->exists) @method('PUT') @endif
        <div class="grid grid-2">
            <label>{{ __('Category') }}
                <select name="category_id">
                    <option value="">{{ __('Uncategorized') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string)old('category_id', $question->category_id) === (string)$category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Subject / Topic') }}<input name="subject" maxlength="120" value="{{ old('subject', $question->subject) }}"></label>
            <label>{{ __('Difficulty') }}<select name="difficulty">@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(old('difficulty', $question->difficulty) === $level)>{{ __(ucfirst($level)) }}</option>@endforeach</select></label>
            <label>{{ __('Status') }}<select name="status"><option value="draft" @selected(old('status', $question->status) === 'draft')>{{ __('Draft') }}</option><option value="published" @selected(old('status', $question->status) === 'published')>{{ __('Published') }}</option></select></label>
        </div>
        <label>{{ __('Question') }}<textarea name="question" rows="4" maxlength="5000" required>{{ old('question', $question->question) }}</textarea></label>
        <div class="grid grid-2">
            @for($i = 0; $i < 4; $i++)
                <label>{{ __('Option') }} {{ ['A','B','C','D'][$i] }}<input name="options[{{ $i }}]" maxlength="500" required value="{{ old('options.'.$i, $question->options[$i] ?? '') }}"></label>
            @endfor
        </div>
        <div class="grid grid-3">
            <label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A','B','C','D'] as $index => $label)<option value="{{ $index }}" @selected((string)old('correct_option', $question->correct_option ?? 0) === (string)$index)>{{ $label }}</option>@endforeach</select></label>
            <label>{{ __('Positive marks') }}<input type="number" name="marks" min="1" max="100" required value="{{ old('marks', $question->marks ?? 1) }}"></label>
            <label>{{ __('Negative marks') }}<input type="number" name="negative_marks" min="0" max="100" required value="{{ old('negative_marks', $question->negative_marks ?? 0) }}"></label>
        </div>
        <label>{{ __('Explanation (optional)') }}<textarea name="explanation" rows="3" maxlength="3000">{{ old('explanation', $question->explanation) }}</textarea></label>
        <button class="btn primary" type="submit">{{ $question->exists ? __('Save changes') : __('Save question') }}</button>
    </form>
</div>
@endsection
