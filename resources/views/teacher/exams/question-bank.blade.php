@extends('layouts.app')

@section('title', __('Question Bank') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ $batch->course->title }}</span><h1 style="margin:12px 0 0;">{{ __('Question Bank') }}</h1><p class="muted">{{ __('Reusable MCQ questions for your exams.') }}</p></div>
        <a href="{{ route('teacher.batches.exams.index', $batch) }}" class="btn secondary">{{ __('Back to exams') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2>{{ __('Add question') }}</h2>
        <form method="POST" action="{{ route('teacher.batches.question-bank.store', $batch) }}">
            @csrf
            <label>{{ __('Subject / topic') }}<input name="subject" maxlength="120" value="{{ old('subject') }}" placeholder="e.g. Grammar"></label>
            <label>{{ __('Question') }}<textarea name="question" required maxlength="3000">{{ old('question') }}</textarea></label>
            <div class="grid grid-2">
                @foreach(['A', 'B', 'C', 'D'] as $index => $option)
                    <label>{{ __('Option') }} {{ $option }}<input name="options[{{ $index }}]" required maxlength="500" value="{{ old('options.'.$index) }}"></label>
                @endforeach
            </div>
            <div class="grid grid-2">
                <label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A', 'B', 'C', 'D'] as $index => $option)<option value="{{ $index }}" {{ (string) old('correct_option', '0') === (string) $index ? 'selected' : '' }}>{{ $option }}</option>@endforeach</select></label>
                <label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="{{ old('marks', 1) }}" required></label>
            </div>
            <button class="btn primary" type="submit">{{ __('Save question') }}</button>
        </form>
    </div>

    <div class="course-list">
        @forelse($questions as $question)
            <article class="course-item">
                <span class="badge info">{{ $question->subject ?: 'General' }}</span>
                <h3>{{ $question->question }}</h3>
                <ol type="A">@foreach($question->options as $option)<li>{{ $option }}</li>@endforeach</ol>
                <p class="muted">{{ __('Correct answer:') }} {{ ['A', 'B', 'C', 'D'][$question->correct_option] }} · {{ $question->marks }} {{ __('marks') }}</p>
                <details>
                    <summary>{{ __('Edit question') }}</summary>
                    <form method="POST" action="{{ route('teacher.batches.question-bank.update', [$batch, $question]) }}" style="margin-top:12px;">
                        @csrf
                        @method('PUT')
                        <label>{{ __('Subject / topic') }}<input name="subject" maxlength="120" value="{{ $question->subject }}"></label>
                        <label>{{ __('Question') }}<textarea name="question" required maxlength="3000">{{ $question->question }}</textarea></label>
                        <div class="grid grid-2">
                            @foreach(['A', 'B', 'C', 'D'] as $index => $option)
                                <label>{{ __('Option') }} {{ $option }}<input name="options[{{ $index }}]" required maxlength="500" value="{{ $question->options[$index] ?? '' }}"></label>
                            @endforeach
                        </div>
                        <div class="grid grid-2">
                            <label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A', 'B', 'C', 'D'] as $index => $option)<option value="{{ $index }}" {{ $question->correct_option === $index ? 'selected' : '' }}>{{ $option }}</option>@endforeach</select></label>
                            <label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="{{ $question->marks }}" required></label>
                        </div>
                        <button class="btn primary" type="submit">{{ __('Update question') }}</button>
                    </form>
                </details>
                <form method="POST" action="{{ route('teacher.batches.question-bank.destroy', [$batch, $question]) }}" style="margin-top:10px;" onsubmit="return confirm('{{ __('Delete this question?') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="btn" type="submit" style="background:#b42318;color:white;border:none;">{{ __('Delete question') }}</button>
                </form>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('Your question bank is empty. Add your first question above.') }}</p></div>
        @endforelse
    </div>
    {{ $questions->links() }}
@endsection
