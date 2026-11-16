@extends('layouts.app')

@section('title', __('Question Bank') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ $batch->course->title }}</span><h1 style="margin:12px 0 0;">{{ __('Question Bank') }}</h1><p class="muted">{{ __('Reusable MCQ questions for your exams.') }}</p></div>
        <a href="{{ $isAdmin ? route('admin.batches.exams.index', $batch) : route('teacher.batches.exams.index', $batch) }}" class="btn secondary">{{ __('Back to exams') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2>{{ __('Add question') }}</h2>
        <form method="POST" action="{{ $isAdmin ? route('admin.batches.question-bank.store', $batch) : route('teacher.batches.question-bank.store', $batch) }}">
            @csrf
            <label>{{ __('Subject / topic') }}<input name="subject" maxlength="120" placeholder="e.g. Grammar"></label>
            <div class="grid grid-2">
                <label>{{ __('Category') }}<input name="category" maxlength="80" placeholder="Grammar, Vocabulary..."></label>
                <label>{{ __('Difficulty') }}<select name="difficulty"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></label>
            </div>
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

    <form method="GET" class="panel" style="margin-bottom:18px;">
        <div class="grid grid-2">
            <label>{{ __('Search') }}<input name="search" value="{{ request('search') }}" placeholder="{{ __('Search question or subject') }}"></label>
            <label>{{ __('Subject') }}<select name="subject"><option value="">{{ __('All subjects') }}</option>@foreach($subjects as $subject)<option value="{{ $subject }}" @selected(request('subject') === $subject)>{{ $subject }}</option>@endforeach</select></label>
            <label>{{ __('Category') }}<select name="category"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select></label>
            <label>{{ __('Difficulty') }}<select name="difficulty"><option value="">{{ __('All levels') }}</option>@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(request('difficulty') === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label>
        </div>
        <button class="btn primary" type="submit">{{ __('Filter questions') }}</button>
        <a class="btn secondary" href="{{ $isAdmin ? route('admin.batches.question-bank.index', $batch) : route('teacher.batches.question-bank.index', $batch) }}">{{ __('Clear') }}</a>
    </form>

    <div class="course-list">
        @forelse($questions as $question)
            <article class="course-item">
                <span class="badge info">{{ $question->subject ?: 'General' }}</span>
                <h3>{{ $question->question }}</h3>
                <ol type="A">@foreach($question->options as $option)<li>{{ $option }}</li>@endforeach</ol>
                <p class="muted">{{ __('Correct answer:') }} {{ ['A', 'B', 'C', 'D'][$question->correct_option] }} · {{ $question->marks }} marks · {{ ucfirst($question->difficulty ?? 'medium') }}</p>
                <details><summary>{{ __('Edit question') }}</summary>
                    <form method="POST" action="{{ $isAdmin ? route('admin.batches.question-bank.update', [$batch, $question]) : route('teacher.batches.question-bank.update', [$batch, $question]) }}">
                        @csrf @method('PUT')
                        <label>{{ __('Subject') }}<input name="subject" value="{{ $question->subject }}"></label>
                        <label>{{ __('Category') }}<input name="category" value="{{ $question->category }}"></label>
                        <label>{{ __('Difficulty') }}<select name="difficulty">@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(($question->difficulty ?? 'medium') === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label>
                        <label>{{ __('Question') }}<textarea name="question" required>{{ $question->question }}</textarea></label>
                        <div class="grid grid-2">@foreach($question->options as $i => $option)<label>Option {{ ['A','B','C','D'][$i] }}<input name="options[{{ $i }}]" value="{{ $option }}" required></label>@endforeach</div>
                        <div class="grid grid-2"><label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A','B','C','D'] as $i => $letter)<option value="{{ $i }}" @selected($question->correct_option === $i)>{{ $letter }}</option>@endforeach</select></label><label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="{{ $question->marks }}"></label></div>
                        <button class="btn primary" type="submit">{{ __('Update') }}</button>
                    </form>
                </details>
                <form method="POST" action="{{ $isAdmin ? route('admin.batches.question-bank.destroy', [$batch, $question]) : route('teacher.batches.question-bank.destroy', [$batch, $question]) }}" onsubmit="return confirm('{{ __('Delete this question?') }}')">
                    @csrf @method('DELETE') <button class="btn secondary" type="submit">{{ __('Delete') }}</button>
                </form>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('Your question bank is empty. Add your first question above.') }}</p></div>
        @endforelse
    </div>
    {{ $questions->links() }}
@endsection
