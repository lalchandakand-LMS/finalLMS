@extends('layouts.app')

@section('title', 'Exams | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ $batch->course->title }}</span><h1 style="margin:12px 0 0;">{{ $batch->title }} exams</h1></div>
        <a href="{{ $isAdmin ? route('admin.batches.index') : route('teacher.batches.index') }}" class="btn secondary">{{ $isAdmin ? __('All batches') : __('My batches') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Create multiple-choice exam') }}</h2>
        <p><a class="btn secondary" href="{{ $isAdmin ? route('admin.batches.question-bank.index', $batch) : route('teacher.batches.question-bank.index', $batch) }}">{{ __('Manage Question Bank') }}</a></p>
        <p class="muted">Add one or more questions. Each question has four options and one correct answer. Marks are awarded automatically.</p>
        <form method="POST" action="{{ $isAdmin ? route('admin.batches.exams.store', $batch) : route('teacher.batches.exams.store', $batch) }}">
            @csrf
            <label>{{ __('Exam title') }}<input name="title" maxlength="180" required></label>
            <label>{{ __('Instructions') }}<textarea name="instructions" maxlength="5000"></textarea></label>
            <div class="grid grid-2">
                <label>{{ __('Starts at') }}<input type="datetime-local" name="starts_at" required></label>
                <label>{{ __('Ends at') }}<input type="datetime-local" name="ends_at" required></label>
                <label>{{ __('Duration (minutes)') }}<input type="number" name="duration_minutes" min="1" max="600" required></label>
                <label>{{ __('Pass marks') }}<input type="number" name="pass_marks" min="0" value="0" required></label>
                <label>{{ __('Questions per student attempt (optional)') }}<input type="number" name="questions_per_attempt" min="1" max="100" placeholder="{{ __('Leave blank to use all questions') }}"></label>
            </div>
            <label><input type="checkbox" id="use-question-bank"> {{ __('Select random questions from my Question Bank') }}</label>
            <div id="question-bank-area" hidden>
                <label>{{ __('Number of random questions') }}<input type="number" name="random_question_count" min="1" max="100" value="10"></label>
                @forelse($bankQuestions as $bankQuestion)
                    <label style="display:block;"><input type="checkbox" name="question_bank_ids[]" value="{{ $bankQuestion->id }}"> {{ $bankQuestion->subject ?: 'General' }} — {{ Str::limit($bankQuestion->question, 120) }} ({{ $bankQuestion->marks }} marks)</label>
                @empty
                    <p class="muted">Question Bank is empty. <a href="{{ $isAdmin ? route('admin.batches.question-bank.index', $batch) : route('teacher.batches.question-bank.index', $batch) }}">Add questions</a>.</p>
                @endforelse
            </div>
            <div id="manual-question-area">
            <div id="exam-questions">
                <div class="course-item exam-question">
                    <h3>Question 1</h3>
                    <label>Question<textarea name="questions[0][question]" required maxlength="3000"></textarea></label>
                    <div class="grid grid-2">
                        <label>Option A<input name="questions[0][options][0]" required></label>
                        <label>Option B<input name="questions[0][options][1]" required></label>
                        <label>Option C<input name="questions[0][options][2]" required></label>
                        <label>Option D<input name="questions[0][options][3]" required></label>
                    </div>
                    <div class="grid grid-2">
                        <label>Correct option<select name="questions[0][correct_option]"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label>
                        <label>Marks<input type="number" name="questions[0][marks]" min="1" max="100" value="1" required></label>
                    </div>
                </div>
            </div>
            </div>
            <button type="button" class="btn secondary" id="add-exam-question">{{ __('Add question') }}</button>
            <button type="submit" class="btn primary">{{ __('Publish exam') }}</button>
        </form>
    </div>

    <div class="course-list">
        @forelse($exams as $exam)
            <article class="course-item">
                <span class="badge {{ $exam->status === 'published' ? 'success' : 'warning' }}">{{ ucfirst($exam->status) }}</span>
                <h2>{{ $exam->title }}</h2>
                <p class="muted">{{ $exam->questions_count }} questions · {{ $exam->attempts_count }} attempts · {{ $exam->duration_minutes }} minutes</p>
                <p class="muted">{{ $exam->starts_at->format('M j, Y H:i') }} – {{ $exam->ends_at->format('M j, Y H:i') }}</p>
                <a class="btn secondary" href="{{ $isAdmin ? route('admin.exams.results', $exam) : route('teacher.exams.results', $exam) }}">{{ __('View results') }}</a>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No exams have been published for this batch.') }}</p></div>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const container = document.getElementById('exam-questions');
    const button = document.getElementById('add-exam-question');
    const bankToggle = document.getElementById('use-question-bank');
    const bankArea = document.getElementById('question-bank-area');
    const manualArea = document.getElementById('manual-question-area');
    const syncQuestionMode = () => {
        const useBank = bankToggle.checked;
        bankArea.hidden = !useBank;
        bankArea.querySelectorAll('input').forEach(field => field.disabled = !useBank);
        manualArea.hidden = false;

        manualArea.querySelectorAll('.exam-question').forEach(card => {
            const questionField = card.querySelector('textarea[name$="[question]"]');
            const optionFields = [...card.querySelectorAll('input[name*="[options]"]')];
            const hasManualContent = Boolean(
                questionField?.value.trim() || optionFields.some(field => field.value.trim())
            );

            card.querySelectorAll('input, textarea, select').forEach(field => {
                field.disabled = false;
                if (field.matches('textarea[name$="[question]"], input[name*="[options]"], input[name$="[marks]"]')) {
                    field.required = !useBank || hasManualContent;
                }
            });
        });

        button.hidden = false;
    };
    bankToggle.addEventListener('change', syncQuestionMode);
    manualArea.addEventListener('input', syncQuestionMode);
    syncQuestionMode();

    const examForm = bankToggle.closest('form');
    examForm.addEventListener('submit', () => {
        if (!bankToggle.checked) return;

        manualArea.querySelectorAll('.exam-question').forEach(card => {
            const questionField = card.querySelector('textarea[name$="[question]"]');
            const optionFields = [...card.querySelectorAll('input[name*="[options]"]')];
            const hasManualContent = Boolean(
                questionField?.value.trim() || optionFields.some(field => field.value.trim())
            );

            if (!hasManualContent) {
                card.querySelectorAll('input, textarea, select').forEach(field => field.disabled = true);
            }
        });
    });
    if (!container || !button) return;
    button.addEventListener('click', () => {
        const index = container.querySelectorAll('.exam-question').length;
        const card = document.createElement('div');
        card.className = 'course-item exam-question';
        card.innerHTML = `
            <h3>Question ${index + 1}</h3>
            <label>Question<textarea name="questions[${index}][question]" required maxlength="3000"></textarea></label>
            <div class="grid grid-2">
                <label>Option A<input name="questions[${index}][options][0]" required></label>
                <label>Option B<input name="questions[${index}][options][1]" required></label>
                <label>Option C<input name="questions[${index}][options][2]" required></label>
                <label>Option D<input name="questions[${index}][options][3]" required></label>
            </div>
            <div class="grid grid-2">
                <label>Correct option<select name="questions[${index}][correct_option]"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label>
                <label>Marks<input type="number" name="questions[${index}][marks]" min="1" max="100" value="1" required></label>
            </div>
            <button type="button" class="btn secondary remove-question">Remove question</button>`;
        container.appendChild(card);
        syncQuestionMode();
    });
    container.addEventListener('click', event => {
        if (event.target.classList.contains('remove-question')) event.target.closest('.exam-question').remove();
    });
})();
</script>
@endpush
