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
            @endphp
            <p class="badge warning">Attempt in progress · {{ ceil($remaining / 60) }} minutes remaining</p>
            <form method="POST" action="{{ route('student.exams.submit', $attempt) }}">
                @csrf
                @foreach($exam->questions as $question)
                    <div class="course-item" style="margin:16px 0;">
                        <h3>{{ $loop->iteration }}. {{ $question->question }}</h3>
                        <p class="muted">{{ $question->marks }} marks</p>
                        @foreach($question->options as $index => $option)
                            <label style="font-weight:400;"><input style="width:auto;margin-right:8px;" type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}" required> {{ chr(65 + $index) }}. {{ $option }}</label>
                        @endforeach
                    </div>
                @endforeach
                <button class="btn primary" type="submit">Submit exam</button>
            </form>
        @elseif($exam->status === 'published' && now()->between($exam->starts_at, $exam->ends_at))
            <p>This exam allows one attempt. The timer starts when you begin.</p>
            <form method="POST" action="{{ route('student.exams.start', $exam) }}">@csrf<button class="btn primary" type="submit">Start exam</button></form>
        @else
            <p class="muted">This exam is not currently available.</p>
        @endif
    </div>
@endsection
