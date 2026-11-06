@extends('layouts.app')

@section('title', $assignment->title . ' | ' . config('app.name'))

@section('content')
    <div class="panel" style="max-width:900px; margin:0 auto;">
        <span class="badge info">{{ $assignment->batch->course->title }}</span>
        <h1>{{ $assignment->title }}</h1>
        <p>{{ $assignment->instructions }}</p>
        <p class="muted">Due {{ $assignment->due_at->format('M j, Y H:i') }} · {{ $assignment->max_marks }} marks · {{ $assignment->allow_late ? __('Late submissions allowed') : __('Late submissions closed') }}</p>
        @if($submission)
            <span class="badge {{ $submission->status === 'graded' ? 'success' : 'warning' }}">{{ ucfirst($submission->status) }}</span>
            @if($submission->answer)<p>{{ $submission->answer }}</p>@endif
            @if($submission->attachment_path)<p><a class="btn secondary" href="{{ route('assignment-submissions.attachment', $submission) }}">Download submitted file: {{ $submission->attachment_name }}</a></p>@endif
            @if($submission->status === 'graded')
                <p><strong>{{ __('Score:') }}</strong> {{ $submission->score }} / {{ $assignment->max_marks }}</p>
                <p>{{ $submission->feedback }}</p>
                @if($assignment->status === 'published' && (! $assignment->due_at->isPast() || $assignment->allow_late))
                    <form method="POST" enctype="multipart/form-data" action="{{ route('student.assignments.submit', $assignment) }}">
                        @csrf
                        <label>{{ __('Resubmit answer') }}<textarea name="answer" maxlength="50000"></textarea></label>
                        <label>{{ __('Replace attachment (PDF, DOC, DOCX, JPG, PNG; max 10 MB)') }}<input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></label>
                        <button type="submit" class="btn primary">{{ __('Resubmit assignment') }}</button>
                    </form>
                @endif
            @endif
        @elseif(! $submission && $assignment->status === 'published' && (! $assignment->due_at->isPast() || $assignment->allow_late))
            <form method="POST" enctype="multipart/form-data" action="{{ route('student.assignments.submit', $assignment) }}">
                @csrf
                <label for="assignment-answer">{{ __('Your answer') }}</label>
                <textarea id="assignment-answer" name="answer" maxlength="50000"></textarea>
                <label for="assignment-attachment">{{ __('Attach file (PDF, DOC, DOCX, JPG, PNG; max 10 MB)') }}</label>
                <input id="assignment-attachment" type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <button type="submit" class="btn primary">{{ __('Submit assignment') }}</button>
            </form>
        @elseif(! $submission)
            <p class="muted">{{ __('This assignment is closed for submissions.') }}</p>
        @endif
    </div>
@endsection