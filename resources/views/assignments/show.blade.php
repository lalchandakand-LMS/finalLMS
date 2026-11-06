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
            @if($submission->answer)<p>{{ $submission->answer }}</p>@endif
            @if($submission->attachment_path)<p><a class="btn secondary" href="{{ route('assignment-submissions.attachment', $submission) }}">Download submitted file: {{ $submission->attachment_name }}</a></p>@endif
            @if($submission->status === 'graded')
                <p><strong>Score:</strong> {{ $submission->score }} / {{ $assignment->max_marks }}</p>
                <p>{{ $submission->feedback }}</p>
            @endif
        @else
            <form method="POST" enctype="multipart/form-data" action="{{ route('student.assignments.submit', $assignment) }}">
                @csrf
                <label for="assignment-answer">Your answer</label>
                <textarea id="assignment-answer" name="answer" maxlength="50000"></textarea>
                <label for="assignment-attachment">Attach file (PDF, DOC, DOCX, JPG, PNG; max 10 MB)</label>
                <input id="assignment-attachment" type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <button type="submit" class="btn primary">Submit assignment</button>
            </form>
        @endif
    </div>
@endsection