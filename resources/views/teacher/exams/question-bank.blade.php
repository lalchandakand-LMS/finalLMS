@extends('layouts.app')

@section('title', __('Question Bank') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ $batch->course->title }}</span><h1 style="margin:12px 0 0;">{{ __('Question Bank') }}</h1><p class="muted">{{ __('Reusable MCQ, True/False, short-answer and written questions for your exams.') }}</p></div>
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
            <label>{{ __('Question type') }}<select name="type" class="question-type"><option value="mcq">Multiple choice</option><option value="true_false">True / False</option><option value="short_answer">Short answer</option><option value="written">Written response</option></select></label>
            <label>{{ __('Question') }}<textarea name="question" required maxlength="3000"></textarea></label>
            <div class="grid grid-2 question-options">
                <label>Option A<input name="options[0]" required maxlength="500"></label>
                <label>Option B<input name="options[1]" required maxlength="500"></label>
                <label>Option C<input name="options[2]" required maxlength="500"></label>
                <label>Option D<input name="options[3]" required maxlength="500"></label>
            </div>
            <div class="grid grid-2 question-answer">
                <label>{{ __('Correct option') }}<select name="correct_option"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label>
                <label>{{ __('Positive marks') }}<input type="number" name="marks" min="1" max="100" value="1" required></label>
                <label>{{ __('Negative marks') }}<input type="number" name="negative_marks" min="0" max="100" value="0" required></label>
            </div>
            <label class="question-correct-answer" hidden>{{ __('Expected short answer') }}<input name="correct_answer" maxlength="2000"></label>
            <label class="question-rubric" hidden>{{ __('Written grading rubric (one criterion per line)') }}<textarea name="grading_rubric_text" rows="3"></textarea></label>
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
                <span class="badge info">{{ $question->subject ?: 'General' }}</span><span class="badge">{{ ucfirst(str_replace('_', ' ', $question->type ?: 'mcq')) }}</span>
                <h3>{{ $question->question }}</h3>
                @if(in_array($question->type ?: 'mcq', ['mcq','true_false'], true))<ol type="A">@foreach($question->options ?? [] as $option)<li>{{ $option }}</li>@endforeach</ol>@endif
                <p class="muted">{{ __('Answer key:') }} {{ in_array($question->type ?: 'mcq', ['mcq','true_false'], true) ? ($question->options[$question->correct_option] ?? '—') : ($question->correct_answer ?: __('Manual grading')) }} · {{ $question->marks }} marks · {{ $question->negative_marks }} negative · {{ ucfirst($question->difficulty ?? 'medium') }}</p>
                <details><summary>{{ __('Edit question') }}</summary>
                    <form method="POST" action="{{ $isAdmin ? route('admin.batches.question-bank.update', [$batch, $question]) : route('teacher.batches.question-bank.update', [$batch, $question]) }}">
                        @csrf @method('PUT')
                        <label>{{ __('Subject') }}<input name="subject" value="{{ $question->subject }}"></label>
                        <label>{{ __('Category') }}<input name="category" value="{{ $question->category }}"></label>
                        <label>{{ __('Difficulty') }}<select name="difficulty">@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(($question->difficulty ?? 'medium') === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label>
                                                <label>{{ __('Question type') }}<select name="type" class="question-type">@foreach(['mcq'=>'Multiple choice','true_false'=>'True / False','short_answer'=>'Short answer','written'=>'Written response'] as $type => $label)<option value="{{ $type }}" @selected(($question->type ?: 'mcq') === $type)>{{ $label }}</option>@endforeach</select></label>
                        <label>{{ __('Question') }}<textarea name="question" required>{{ $question->question }}</textarea></label>
                        <div class="grid grid-2 question-options">@foreach($question->options ?? [] as $i => $option)<label>Option {{ ['A','B','C','D'][$i] ?? $i+1 }}<input name="options[{{ $i }}]" value="{{ $option }}" required></label>@endforeach</div>
                        <div class="grid grid-2 question-answer"><label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A','B','C','D'] as $i => $letter)<option value="{{ $i }}" @selected($question->correct_option === $i)>{{ $letter }}</option>@endforeach</select></label></div>
                        <label class="question-correct-answer" hidden>{{ __('Expected short answer') }}<input name="correct_answer" value="{{ $question->correct_answer }}" maxlength="2000"></label>
                        <label>{{ __('Explanation') }}<textarea name="explanation">{{ $question->explanation }}</textarea></label>
                        <div class="grid grid-2"><label>{{ __('Positive marks') }}<input type="number" name="marks" min="1" max="100" value="{{ $question->marks }}"></label><label>{{ __('Negative marks') }}<input type="number" name="negative_marks" min="0" max="100" value="{{ $question->negative_marks }}"></label></div>
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
@push('scripts')
<script>
(() => {
    document.querySelectorAll('form').forEach(form => {
        const type = form.querySelector('.question-type');
        if (!type) return;
        const options = form.querySelector('.question-options');
        const answer = form.querySelector('.question-answer');
        const expected = form.querySelector('.question-correct-answer');
        const rubric = form.querySelector('.question-rubric');
        const sync = () => {
            const value = type.value;
            const choice = ['mcq','true_false'].includes(value);
            if (options) options.hidden = !choice;
            if (answer) answer.hidden = !choice;
            if (expected) expected.hidden = value !== 'short_answer';
            if (rubric) rubric.hidden = value !== 'written';
            options?.querySelectorAll('input').forEach(input => { input.disabled = !choice || value === 'true_false'; input.required = value === 'mcq'; });
            answer?.querySelectorAll('select').forEach(input => { input.disabled = !choice; input.required = choice; });
            if (expected) { const input = expected.querySelector('input'); input.required = value === 'short_answer'; input.disabled = value !== 'short_answer'; }
        };
        type.addEventListener('change', sync); sync();
    });
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        const rubric = form.querySelector('[name="grading_rubric_text"]');
        if (rubric && rubric.value.trim()) {
            rubric.value.split('\n').map(v => v.trim()).filter(Boolean).forEach((value, i) => {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = 'grading_rubric['+i+']'; input.value = value; form.appendChild(input);
            });
        }
    }));
})();
</script>
@endpush
@endsection
