@extends('layouts.app')

@section('title', __('Quiz attempt') . ' | ' . $questionSet->title)

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Course quiz') }}</span>
            <h1 style="margin:10px 0 0;">{{ $questionSet->title }}</h1>
            <p class="muted">{{ $course->title }} · {{ __('Attempt') }} #{{ $attempt->attempt_number }}</p>
        </div>
        <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a>
    </div>

    @if(session('success'))
        <div class="alert success" role="status">{{ session('success') }}</div>
    @endif
    @if($attempt->status === 'in_progress')
        <div class="panel" style="margin-bottom:18px;">
            <div class="meta-row">
                <strong>{{ __('Total marks:') }} {{ $attempt->max_score }}</strong>
                @if($attempt->deadline_at)
                    <span class="badge warning">{{ __('Deadline:') }} {{ $attempt->deadline_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                    <span id="quiz-countdown" class="badge info" role="timer">{{ __('Loading timer...') }}</span>
                @endif
            </div>
            <p class="muted">{{ __('Your answers are saved automatically while you work. Review them before pressing Submit quiz.') }}</p>
            <p id="quiz-save-status" class="muted" role="status" aria-live="polite">{{ __('Answers not changed yet.') }}</p>
        </div>

        <form method="POST" action="{{ route('student.question-sets.submit', [$course, $questionSet, $attempt]) }}" data-save-url="{{ route('student.question-sets.save', [$course, $questionSet, $attempt]) }}" id="question-set-attempt-form" class="panel">
            @csrf
            @foreach($questions as $index => $question)
                <fieldset style="border:1px solid var(--border);border-radius:12px;padding:16px;margin:0 0 16px;">
                    <legend style="padding:0 8px;font-weight:800;">{{ $index + 1 }}. {{ $question['question'] }}</legend>
                    <p class="muted">{{ __('Marks:') }} {{ $question['marks'] }}</p>
                    @if(in_array($question['type'], ['mcq', 'true_false'], true))
                        @foreach($question['options'] ?? [] as $optionIndex => $option)
                            <label style="display:flex;align-items:center;gap:10px;margin:10px 0;">
                                <input type="radio" name="answers[{{ $question['id'] }}][selected_option]" value="{{ $optionIndex }}" @checked((string) old('answers.'.$question['id'].'.selected_option', $question['selected_option'] ?? '') === (string) $optionIndex)>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    @else
                        <label for="answer-{{ $question['id'] }}">{{ __('Your answer') }}</label>
                        <textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}][answer_text]" rows="3" maxlength="5000">{{ old('answers.'.$question['id'].'.answer_text', $question['answer_text'] ?? '') }}</textarea>
                    @endif
                    @error('answers.'.$question['id'].'.selected_option')<p class="error">{{ $message }}</p>@enderror
                    @error('answers.'.$question['id'].'.answer_text')<p class="error">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
            @error('answers')<p class="error">{{ $message }}</p>@enderror
            <button type="submit" class="btn primary">{{ __('Submit quiz') }}</button>
        </form>
        <script>
        (() => {
            const form = document.getElementById('question-set-attempt-form');
            const status = document.getElementById('quiz-save-status');
            const timerLabel = document.getElementById('quiz-countdown');
            if (!form) return;

            let saveTimer = null;
            let countdownTimer = null;
            let saving = false;
            const saveUrl = form.dataset.saveUrl;

            const saveAnswers = async () => {
                if (saving) return;
                saving = true;
                status.textContent = '{{ __('Saving answers...') }}';
                try {
                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        body: new FormData(form),
                        credentials: 'same-origin'
                    });
                    if (response.status === 409) {
                        status.textContent = '{{ __('Time expired. Your saved answers have been finalized.') }}';
                        window.location.reload();
                        return;
                    }
                    if (!response.ok) throw new Error('save_failed');
                    status.textContent = '{{ __('All answers saved.') }}';
                } catch (error) {
                    status.textContent = '{{ __('Could not save just now. Your answers will be retried.') }}';
                } finally {
                    saving = false;
                }
            };

            const scheduleSave = () => {
                if (saveTimer) window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(saveAnswers, 700);
            };
            form.addEventListener('input', scheduleSave);
            form.addEventListener('change', scheduleSave);
            form.addEventListener('submit', () => {
                if (saveTimer) window.clearTimeout(saveTimer);
                if (countdownTimer) window.clearInterval(countdownTimer);
                status.textContent = '{{ __('Submitting quiz...') }}';
            });

            @if($attempt->deadline_at)
                const deadline = new Date(@json($attempt->deadline_at->toIso8601String())).getTime();
                const tick = () => {
                    const remaining = Math.max(0, deadline - Date.now());
                    const minutes = Math.floor(remaining / 60000);
                    const seconds = Math.floor((remaining % 60000) / 1000);
                    timerLabel.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                    if (remaining <= 0) {
                        if (countdownTimer) window.clearInterval(countdownTimer);
                        form.requestSubmit();
                    }
                };
                tick();
                countdownTimer = window.setInterval(tick, 1000);
            @else
                if (timerLabel) timerLabel.textContent = '{{ __('No time limit') }}';
            @endif
        })();
        </script>
    @else
        <section class="panel">
            @if($attempt->status === 'awaiting_manual')
                <span class="badge warning">{{ __('Awaiting teacher review') }}</span>
                <h2>{{ __('Your answers have been submitted') }}</h2>
                <p>{{ __('Objective questions have been checked. Your final result will appear after the written answers are reviewed.') }}</p>
                <p>{{ __('Objective score:') }} <strong>{{ $attempt->auto_score }} / {{ $attempt->max_score }}</strong></p>
            @elseif($attempt->status === 'graded')
                <span class="badge success">{{ __('Graded') }}</span>
                <h2>{{ __('Final quiz result') }}</h2>
                <p>{{ __('Score:') }} <strong>{{ $attempt->final_score }} / {{ $attempt->max_score }}</strong></p>
                @if($attempt->teacher_feedback)<p><strong>{{ __('Teacher feedback:') }}</strong> {{ $attempt->teacher_feedback }}</p>@endif
            @else
                <span class="badge success">{{ __('Submitted') }}</span>
                <h2>{{ __('Quiz result') }}</h2>
                <p>{{ __('Score:') }} <strong>{{ $attempt->final_score ?? $attempt->auto_score }} / {{ $attempt->max_score }}</strong></p>
            @endif
            <p class="muted">{{ __('Submitted at:') }} {{ $attempt->submitted_at?->format('M j, Y g:i A') }}</p>
            <div style="margin-top:18px;">
                <h3>{{ __('Your submitted answers') }}</h3>
                @foreach($questions as $index => $question)
                    <div class="course-item" style="margin-bottom:10px;">
                        <strong>{{ $index + 1 }}. {{ $question['question'] }}</strong>
                        @if(in_array($question['type'], ['mcq', 'true_false'], true))
                            <p class="muted">{{ __('Your answer:') }} {{ $question['selected_option'] !== null ? ($question['options'][$question['selected_option']] ?? __('Not answered')) : __('Not answered') }}</p>
                            <p class="muted">{{ __('Marks awarded:') }} {{ $question['marks_awarded'] }} / {{ $question['marks'] }}</p>
                        @else
                            <p>{{ $question['answer_text'] ?: __('Not answered') }}</p>
                            @if($attempt->status === 'graded')
                                <p class="muted">{{ __('Marks awarded:') }} {{ $question['marks_awarded'] }} / {{ $question['marks'] }}</p>
                                @if($question['manual_feedback'])<p><strong>{{ __('Teacher feedback:') }}</strong> {{ $question['manual_feedback'] }}</p>@endif
                            @else
                                <p class="muted">{{ __('Written answer pending teacher review.') }}</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Return to course') }}</a>
        </section>
    @endif
@endsection
