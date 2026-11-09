@extends('layouts.app')

@section('title', $exam->title . ' | ' . config('app.name'))

@section('content')
    <div class="panel" style="max-width:900px;margin:0 auto;">
        <span class="badge info">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</span>
        <h1>{{ $exam->title }}</h1>
        @if($exam->instructions)<p>{{ $exam->instructions }}</p>@endif
        <p class="muted">Available {{ $exam->starts_at->format('M j, Y H:i') }} – {{ $exam->ends_at->format('M j, Y H:i') }} · {{ $exam->duration_minutes }} minutes</p>

        @if($attempt && $attempt->status === 'submitted')
            <span class="badge success">Submitted</span>
            <h2>Your score: {{ $attempt->score }}</h2>
            <p>Pass marks: {{ $exam->pass_marks }} · Result: {{ $attempt->score >= $exam->pass_marks ? 'Passed' : 'Not passed' }}</p>
        @elseif($attempt && $attempt->status === 'in_progress')
            @php
                $deadline = min($exam->ends_at->getTimestamp(), $attempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp());
                $remaining = max(0, $deadline - now()->getTimestamp());
                $savedAnswers = $attempt->answers->keyBy('exam_question_id');
            @endphp
            <p class="badge warning">Attempt in progress · <strong id="exam-countdown" data-deadline="{{ $deadline }}">{{ gmdate('H:i:s', $remaining) }}</strong> remaining</p>
            <p id="exam-save-status" class="muted" role="status" aria-live="polite">Your answers will be saved automatically.</p>
            <form id="exam-submit-form" method="POST" action="{{ route('student.exams.submit', $attempt) }}" data-save-url="{{ route('student.exams.answers.save', $attempt) }}">
                @csrf
                @foreach($attempt->attemptQuestions as $attemptQuestion)
                    @php($question = $attemptQuestion->question)
                    @if($question)
                    <div class="course-item" style="margin:16px 0;">
                        <h3>{{ $loop->iteration }}. {{ $question->question }}</h3>
                        <p class="muted">{{ $question->marks }} marks</p>
                        @foreach($question->options as $index => $option)
                            <label style="font-weight:400;"><input style="width:auto;margin-right:8px;" type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}" @checked(isset($savedAnswers[$question->id]) && (int) $savedAnswers[$question->id]->selected_option === $index)> {{ chr(65 + $index) }}. {{ $option }}</label>
                        @endforeach
                    </div>
                    @endif
                @endforeach
                <button class="btn primary" type="submit">Submit exam</button>
            </form>
            <script>
                (() => {
                    const form = document.getElementById('exam-submit-form');
                    const countdown = document.getElementById('exam-countdown');
                    const status = document.getElementById('exam-save-status');
                    if (!form || !countdown || !status) return;
                    const csrf = form.querySelector('input[name=_token]').value;
                    let saveTimer;
                    let saving = false;
                    let pending = false;
                    let savePromise = Promise.resolve();
                    const save = () => {
                        if (saving) { pending = true; return savePromise; }
                        saving = true;
                        status.textContent = 'Saving answers…';
                        const answers = {};
                        for (const [key, value] of new FormData(form).entries()) {
                            const match = key.match(/^answers\[(\d+)\]$/);
                            if (match) answers[match[1]] = value;
                        }
                        savePromise = fetch(form.dataset.saveUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ answers })
                        }).then(response => {
                            if (!response.ok) throw new Error('Save failed');
                            status.textContent = 'All answers saved';
                        }).catch(() => {
                            status.textContent = 'Could not save answers. Check your connection.';
                        }).finally(() => {
                            saving = false;
                            if (pending) { pending = false; return save(); }
                        });
                        return savePromise;
                    };
                    form.addEventListener('change', () => {
                        clearTimeout(saveTimer);
                        saveTimer = setTimeout(save, 500);
                    });
                    const tick = () => {
                        const seconds = Math.max(0, Number(countdown.dataset.deadline) - Math.floor(Date.now() / 1000));
                        const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
                        const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
                        const secs = String(seconds % 60).padStart(2, '0');
                        countdown.textContent = hours + ':' + minutes + ':' + secs;
                        if (seconds === 0) {
                            save().finally(() => form.submit());
                            return;
                        }
                        if (seconds <= 10) status.textContent = 'Time is almost over. Submitting automatically…';
                    };
                    tick();
                    setInterval(tick, 1000);
                })();
            </script>
        @elseif($exam->status === 'published' && now()->between($exam->starts_at, $exam->ends_at))
            <p>This exam allows one attempt. The timer starts when you begin.</p>
            <form method="POST" action="{{ route('student.exams.start', $exam) }}">@csrf<button class="btn primary" type="submit">Start exam</button></form>
        @else
            <p class="muted">This exam is not currently available.</p>
        @endif
    </div>
@endsection
