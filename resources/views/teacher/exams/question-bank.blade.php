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
            <label>{{ __('Subject / topic') }}<input name="subject" maxlength="120" placeholder="e.g. Grammar"></label>
            <label>{{ __('Question') }}<textarea name="question" required maxlength="3000"></textarea></label>
            <div class="grid grid-2">
                <label>Option A<input name="options[0]" required maxlength="500"></label>
                <label>Option B<input name="options[1]" required maxlength="500"></label>
                <label>Option C<input name="options[2]" required maxlength="500"></label>
                <label>Option D<input name="options[3]" required maxlength="500"></label>
            </div>
            <div class="grid grid-2">
                <label>{{ __('Correct option') }}<select name="correct_option"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label>
                <label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="1" required></label>
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
                <p class="muted">{{ __('Correct answer:') }} {{ ['A', 'B', 'C', 'D'][$question->correct_option] }} · {{ $question->marks }} marks</p>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('Your question bank is empty. Add your first question above.') }}</p></div>
        @endforelse
    </div>
    {{ $questions->links() }}
@endsection
