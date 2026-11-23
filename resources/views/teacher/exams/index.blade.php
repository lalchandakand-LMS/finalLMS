@extends('layouts.app')

@section('title', 'Exams | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ $batch->course->title }}</span><span class="badge info">{{ $batch->course->academicLevel?->name ?? 'General' }}</span><h1 style="margin:12px 0 0;">{{ $batch->title }} exams</h1></div>
        <a href="{{ $isAdmin ? route('admin.batches.index') : route('teacher.batches.index') }}" class="btn secondary">{{ $isAdmin ? __('All batches') : __('My batches') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Create advanced exam') }}</h2>
        <p><a class="btn secondary" href="{{ $isAdmin ? route('admin.batches.question-bank.index', $batch) : route('teacher.batches.question-bank.index', $batch) }}">{{ __('Manage Question Bank') }}</a></p>
        <p class="muted">Build exams with MCQ, True/False, short-answer and manually graded written questions. Autosave and timed submission are enabled for students.</p>
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
                <label><input type="checkbox" name="grading_rules[negative_marking_enabled]" value="1" checked> {{ __('Enable negative marking') }}</label>
                <label>{{ __('Negative marking multiplier') }}<input type="number" name="grading_rules[negative_marking_multiplier]" min="0" max="5" step="0.25" value="1"></label>
                <label><input type="checkbox" name="grading_rules[score_floor_zero]" value="1" checked> {{ __('Do not allow negative total score') }}</label>
                <label><input type="checkbox" name="grading_rules[short_answer_case_sensitive]" value="1"> {{ __('Short answers are case-sensitive') }}</label>
            </div>
            <label><input type="checkbox" id="use-question-bank"> {{ __('Select random questions from my Question Bank') }}</label>
            <div id="question-bank-area" hidden>
                <label>{{ __('Number of random questions') }}<input type="number" name="random_question_count" min="1" max="100" value="10"></label>
                @forelse($bankQuestions as $bankQuestion)
                    <label style="display:block;"><input type="checkbox" name="question_bank_ids[]" value="{{ $bankQuestion->id }}"> {{ $bankQuestion->subject ?: 'General' }} — {{ Str::limit($bankQuestion->question, 120) }} ({{ $bankQuestion->marks }} positive / {{ $bankQuestion->negative_marks }} negative)</label>
                @empty
                    <p class="muted">Question Bank is empty. <a href="{{ $isAdmin ? route('admin.batches.question-bank.index', $batch) : route('teacher.batches.question-bank.index', $batch) }}">Add questions</a>.</p>
                @endforelse
            </div>
            <div id="manual-question-area">
            <div id="exam-questions">
                <div class="course-item exam-question">
                    <label>{{ __('Question type') }}<select name="questions[0][type]" class="question-type"><option value="mcq">Multiple choice</option><option value="true_false">True / False</option><option value="short_answer">Short answer</option><option value="written">Written response</option></select></label>
                    <label>Question<textarea name="questions[0][question]" required maxlength="3000"></textarea></label>
                    <div class="grid grid-2 question-options">
                        <label>Option A<input name="questions[0][options][0]" required></label><label>Option B<input name="questions[0][options][1]" required></label><label>Option C<input name="questions[0][options][2]" required></label><label>Option D<input name="questions[0][options][3]" required></label>
                    </div>
                    <div class="grid grid-2 question-answer"><label>Correct option<select name="questions[0][correct_option]"><option value="0">A / True</option><option value="1">B / False</option><option value="2">C</option><option value="3">D</option></select></label></div>
                    <label class="question-correct-answer" hidden>Expected short answer<input name="questions[0][correct_answer]" maxlength="2000"></label>
                    <label class="question-rubric" hidden>Written grading rubric (one criterion per line)<textarea class="rubric-text" rows="3"></textarea></label>
                    <label>Explanation<textarea name="questions[0][explanation]" maxlength="3000"></textarea></label>
                    <div class="grid grid-2"><label>Positive marks<input type="number" name="questions[0][marks]" min="1" max="100" value="1" required></label><label>Negative marks<input type="number" name="questions[0][negative_marks]" min="0" max="100" value="0"></label></div>
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
    const form = bankToggle.closest('form');

    const syncCard = card => {
        const type = card.querySelector('.question-type')?.value || 'mcq';
        const choice = ['mcq', 'true_false'].includes(type);
        const options = card.querySelector('.question-options');
        const answer = card.querySelector('.question-answer');
        const expected = card.querySelector('.question-correct-answer');
        const rubric = card.querySelector('.question-rubric');
        if (options) options.hidden = !choice;
        if (answer) answer.hidden = !choice;
        if (expected) expected.hidden = type !== 'short_answer';
        if (rubric) rubric.hidden = type !== 'written';
        options?.querySelectorAll('input').forEach((field, index) => {
            field.disabled = !choice || type === 'true_false';
            field.required = type === 'mcq' && (!bankToggle.checked || Boolean(card.querySelector('textarea[name$=\"[question]\"]')?.value.trim()));
        });
        answer?.querySelectorAll('select').forEach(field => { field.disabled = !choice; });
        if (expected) {
            const field = expected.querySelector('input');
            field.disabled = type !== 'short_answer';
            field.required = type === 'short_answer';
        }
        const rubricText = rubric?.querySelector('.rubric-text');
        if (rubricText) rubricText.disabled = type !== 'written';
    };

    const syncMode = () => {
        const useBank = bankToggle.checked;
        bankArea.hidden = !useBank;
        bankArea.querySelectorAll('input').forEach(field => field.disabled = !useBank);
        manualArea.hidden = false;
        manualArea.querySelectorAll('.exam-question').forEach(card => {
            const questionField = card.querySelector('textarea[name$="[question]"]');
            const hasManualContent = Boolean(questionField?.value.trim());
            card.querySelectorAll('input, textarea, select').forEach(field => {
                if (field.name.includes('[marks]') || field.name.endsWith('[question]')) {
                    field.required = !useBank || hasManualContent;
                }
            });
            syncCard(card);
        });
        button.hidden = false;
    };
    bankToggle.addEventListener('change', syncMode);
    manualArea.addEventListener('input', syncMode);
    manualArea.addEventListener('change', event => {
        if (event.target.matches('.question-type')) syncCard(event.target.closest('.exam-question'));
    });
    syncMode();

    form.addEventListener('submit', () => {
        if (bankToggle.checked) {
            manualArea.querySelectorAll('.exam-question').forEach(card => {
                const questionField = card.querySelector('textarea[name$="[question]"]');
                if (!questionField?.value.trim()) card.querySelectorAll('input, textarea, select').forEach(field => field.disabled = true);
            });
        }
        manualArea.querySelectorAll('.exam-question').forEach(card => {
            const rubric = card.querySelector('.rubric-text');
            if (rubric && rubric.value.trim()) {
                const prefix = card.querySelector('.question-type').name.replace('[type]', '');
                rubric.value.split('\\n').map(value => value.trim()).filter(Boolean).forEach((value, index) => {
                    const hidden = document.createElement('input'); hidden.type = 'hidden';
                    hidden.name = prefix + '[grading_rubric][' + index + ']';
                    hidden.value = value; form.appendChild(hidden);
                });
            }
        });
    });

    button.addEventListener('click', () => {
        const index = container.querySelectorAll('.exam-question').length;
        const card = document.createElement('div');
        card.className = 'course-item exam-question';
        card.innerHTML = `
            <h3>Question ${index + 1}</h3>
            <label>Question type<select name="questions[${index}][type]" class="question-type"><option value="mcq">Multiple choice</option><option value="true_false">True / False</option><option value="short_answer">Short answer</option><option value="written">Written response</option></select></label>
            <label>Question<textarea name="questions[${index}][question]" required maxlength="3000"></textarea></label>
            <div class="grid grid-2 question-options"><label>Option A<input name="questions[${index}][options][0]" required></label><label>Option B<input name="questions[${index}][options][1]" required></label><label>Option C<input name="questions[${index}][options][2]" required></label><label>Option D<input name="questions[${index}][options][3]" required></label></div>
            <div class="grid grid-2 question-answer"><label>Correct option<select name="questions[${index}][correct_option]"><option value="0">A / True</option><option value="1">B / False</option><option value="2">C</option><option value="3">D</option></select></label></div>
            <label class="question-correct-answer" hidden>Expected short answer<input name="questions[${index}][correct_answer]" maxlength="2000"></label>
            <label class="question-rubric" hidden>Written grading rubric (one criterion per line)<textarea class="rubric-text" rows="3"></textarea></label>
            <label>Explanation<textarea name="questions[${index}][explanation]" maxlength="3000"></textarea></label>
            <div class="grid grid-2"><label>Positive marks<input type="number" name="questions[${index}][marks]" min="1" max="100" value="1" required></label><label>Negative marks<input type="number" name="questions[${index}][negative_marks]" min="0" max="100" value="0"></label></div>
            <button type="button" class="btn secondary remove-question">Remove question</button>`;
        container.appendChild(card); syncCard(card); syncMode();
    });
    container.addEventListener('click', event => {
        if (event.target.classList.contains('remove-question')) event.target.closest('.exam-question').remove();
    });
})();
</script>
@endpush
