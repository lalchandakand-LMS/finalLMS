@php
    $selectedSourceIds = old('source_question_ids', $questions->whereNotNull('source_question_id')->pluck('source_question_id')->all());
    $customQuestions = old('questions', $questions->whereNull('source_question_id')->values()->all());
    if (count($customQuestions) === 0) {
        $customQuestions = [['type' => 'mcq', 'question' => '', 'options' => ['', '', '', ''], 'correct_option' => 0, 'marks' => 1, 'difficulty' => 'medium']];
    }
@endphp
<form method="POST" action="{{ $questionSet->exists ? route('admin.question-sets.update', $questionSet) : route('admin.question-sets.store') }}">
    @csrf
    @if($questionSet->exists) @method('PUT') @endif
    <label>{{ __('Set title') }}<input name="title" maxlength="180" value="{{ old('title', $questionSet->title) }}" required></label>
    <label>{{ __('Description') }}<textarea name="description" maxlength="5000" rows="3">{{ old('description', $questionSet->description) }}</textarea></label>
    <label>{{ __('Status') }}<select name="status" required>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $questionSet->status ?: 'draft') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label>

    <div class="panel" style="margin:18px 0;">
        <h2>{{ __('Reuse Question Bank MCQs') }}</h2>
        <p class="muted">{{ __('Selected bank questions are copied into this set as a snapshot. Later edits to the source bank item will not silently change this set.') }}</p>
        @if($bankQuestions->isEmpty())
            <p class="muted">{{ __('No reusable Question Bank items are available yet.') }}</p>
        @else
            <div style="max-height:320px;overflow:auto;">
                @foreach($bankQuestions as $bankQuestion)
                    <label style="display:flex;align-items:flex-start;gap:9px;padding:8px;border-bottom:1px solid var(--border);">
                        <input type="checkbox" name="source_question_ids[]" value="{{ $bankQuestion->id }}" @checked(in_array($bankQuestion->id, $selectedSourceIds)) style="width:auto;margin-top:4px;">
                        <span><strong>{{ $bankQuestion->subject ?: __('General') }}</strong> · {{ $bankQuestion->teacher?->name }}<small class="muted" style="display:block;">{{ \Illuminate\Support\Str::limit($bankQuestion->question, 160) }}</small></span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div><h2 style="margin-bottom:4px;">{{ __('Custom questions') }}</h2><p class="muted">{{ __('Supports multiple choice, true/false and short answer questions.') }}</p></div>
        <button class="btn secondary" type="button" id="add-set-question">{{ __('Add question') }}</button>
    </div>

    <div id="set-questions">
        @foreach($customQuestions as $index => $question)
            @php($type = data_get($question, 'type', 'mcq'))
            <article class="course-item question-set-item" data-question-card style="margin:14px 0;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <h3>{{ __('Question') }} {{ $loop->iteration }}</h3>
                    <button type="button" class="btn secondary remove-set-question">{{ __('Remove') }}</button>
                </div>
                <label>{{ __('Question type') }}<select name="questions[{{ $index }}][type]" data-question-type>@foreach(['mcq' => 'Multiple choice', 'true_false' => 'True / False', 'short_answer' => 'Short answer'] as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ __($label) }}</option>@endforeach</select></label>
                <label>{{ __('Question text') }}<textarea name="questions[{{ $index }}][question]" maxlength="3000" required>{{ data_get($question, 'question') }}</textarea></label>
                <div data-mcq-fields>
                    <div class="grid grid-2">
                        @foreach(range(0, 3) as $optionIndex)
                            <label>{{ __('Option') }} {{ ['A','B','C','D'][$optionIndex] }}<input name="questions[{{ $index }}][options][{{ $optionIndex }}]" maxlength="500" value="{{ data_get($question, 'options.'.$optionIndex) }}"></label>
                        @endforeach
                    </div>
                    <label>{{ __('Correct option') }}<select name="questions[{{ $index }}][correct_option]" data-correct-option>@foreach(['A','B','C','D'] as $optionIndex => $letter)<option value="{{ $optionIndex }}" @selected((string)data_get($question, 'correct_option', 0) === (string)$optionIndex)>{{ $letter }}</option>@endforeach</select></label>
                </div>
                <div data-true-false-fields>
                    <label>{{ __('Correct answer') }}<select name="questions[{{ $index }}][correct_option]" data-correct-option><option value="0" @selected((string)data_get($question, 'correct_option', 0) === '0')>{{ __('True') }}</option><option value="1" @selected((string)data_get($question, 'correct_option', 0) === '1')>{{ __('False') }}</option></select></label>
                </div>
                <div data-short-answer-fields>
                    <label>{{ __('Expected answer') }}<textarea name="questions[{{ $index }}][correct_answer]" maxlength="2000">{{ data_get($question, 'correct_answer') }}</textarea></label>
                </div>
                <div class="grid grid-2">
                    <label>{{ __('Marks') }}<input type="number" name="questions[{{ $index }}][marks]" min="1" max="100" value="{{ data_get($question, 'marks', 1) }}" required></label>
                    <label>{{ __('Difficulty') }}<select name="questions[{{ $index }}][difficulty]">@foreach(['easy','medium','hard'] as $level)<option value="{{ $level }}" @selected(data_get($question, 'difficulty', 'medium') === $level)>{{ __(ucfirst($level)) }}</option>@endforeach</select></label>
                </div>
                <label>{{ __('Explanation') }}<textarea name="questions[{{ $index }}][explanation]" maxlength="3000">{{ data_get($question, 'explanation') }}</textarea></label>
                <label>{{ __('Tags (comma separated)') }}<input name="questions[{{ $index }}][tags]" maxlength="1000" value="{{ is_array(data_get($question, 'tags')) ? implode(', ', data_get($question, 'tags')) : data_get($question, 'tags') }}"></label>
            </article>
        @endforeach
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
        <button class="btn primary" type="submit">{{ __('Save question set') }}</button>
        <a class="btn secondary" href="{{ route('admin.question-sets.index') }}">{{ __('Cancel') }}</a>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const container = document.getElementById('set-questions');
    const addButton = document.getElementById('add-set-question');
    if (!container || !addButton) return;

    const syncCard = card => {
        const type = card.querySelector('[data-question-type]').value;
        const groups = [
            [card.querySelector('[data-mcq-fields]'), type === 'mcq'],
            [card.querySelector('[data-true-false-fields]'), type === 'true_false'],
            [card.querySelector('[data-short-answer-fields]'), type === 'short_answer']
        ];
        groups.forEach(([element, visible]) => {
            if (!element) return;
            element.hidden = !visible;
            element.querySelectorAll('input,textarea,select').forEach(field => field.disabled = !visible);
        });
    };

    container.querySelectorAll('[data-question-card]').forEach(card => {
        syncCard(card);
        card.querySelector('[data-question-type]').addEventListener('change', () => syncCard(card));
    });

    addButton.addEventListener('click', () => {
        const index = Math.max(-1, ...Array.from(container.querySelectorAll('[data-question-type]')).map(select => {
            const match = select.name.match(/^questions\[(\d+)\]/);
            return match ? Number(match[1]) : -1;
        })) + 1;
        const card = document.createElement('article');
        card.className = 'course-item question-set-item';
        card.setAttribute('data-question-card', '');
        card.style.margin = '14px 0';
        card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;"><h3>Question</h3><button type="button" class="btn secondary remove-set-question">Remove</button></div>
            <label>Question type<select name="questions[${index}][type]" data-question-type><option value="mcq">Multiple choice</option><option value="true_false">True / False</option><option value="short_answer">Short answer</option></select></label>
            <label>Question text<textarea name="questions[${index}][question]" maxlength="3000" required></textarea></label>
            <div data-mcq-fields><div class="grid grid-2">
                <label>Option A<input name="questions[${index}][options][0]" maxlength="500"></label><label>Option B<input name="questions[${index}][options][1]" maxlength="500"></label>
                <label>Option C<input name="questions[${index}][options][2]" maxlength="500"></label><label>Option D<input name="questions[${index}][options][3]" maxlength="500"></label>
            </div><label>Correct option<select name="questions[${index}][correct_option]" data-correct-option><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label></div>
            <div data-true-false-fields><label>Correct answer<select name="questions[${index}][correct_option]" data-correct-option><option value="0">True</option><option value="1">False</option></select></label></div>
            <div data-short-answer-fields><label>Expected answer<textarea name="questions[${index}][correct_answer]" maxlength="2000"></textarea></label></div>
            <div class="grid grid-2"><label>Marks<input type="number" name="questions[${index}][marks]" min="1" max="100" value="1" required></label>
            <label>Difficulty<select name="questions[${index}][difficulty]"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></label></div>
            <label>Explanation<textarea name="questions[${index}][explanation]" maxlength="3000"></textarea></label>
            <label>Tags (comma separated)<input name="questions[${index}][tags]" maxlength="1000"></label>`;
        container.appendChild(card);
        syncCard(card);
        card.querySelector('[data-question-type]').addEventListener('change', () => syncCard(card));
    });

    container.addEventListener('click', event => {
        if (event.target.classList.contains('remove-set-question')) {
            const cards = container.querySelectorAll('[data-question-card]');
            if (cards.length > 1) event.target.closest('[data-question-card]').remove();
        }
    });
})();
</script>
@endpush
