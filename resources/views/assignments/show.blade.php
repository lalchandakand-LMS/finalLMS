@extends('layouts.app')

@section('title', $assignment->title . ' | ' . config('app.name'))

@section('content')
    <div class="panel" style="max-width:900px; margin:0 auto;">
        <span class="badge info">{{ $assignment->batch->course->title }}</span>
        <h1>{{ $assignment->title }}</h1>
        <p>{{ $assignment->instructions }}</p>
        <p class="muted">Due {{ $assignment->due_at->format('M j, Y H:i') }} · {{ $assignment->max_marks }} marks</p>
        @if($submission)
            <span class="badge {{ $submission->status === 'graded' ? 'success' : 'warning' }}">{{ ucfirst($submission->status) }}</span>
            <p>{{ $submission->answer }}</p>
            @if($submission->status === 'graded')
                <p><strong>Score:</strong> {{ $submission->score }} / {{ $assignment->max_marks }}</p>
                <p>{{ $submission->feedback }}</p>
            @endif
        @else
            <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}">
                @csrf
                <label for="assignment-answer">Your answer</label>
                <textarea id="assignment-answer" name="answer" maxlength="50000" required></textarea>
                <button type="submit" class="btn primary">Submit assignment</button>
            </form>
        @endif
    </div>
@endsection