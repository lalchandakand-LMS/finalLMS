@extends('layouts.app')

@section('title', 'Assignments | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $batch->course->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $batch->title }} assignments</h1>
        </div>
        <a href="{{ route('teacher.batches.index') }}" class="btn secondary">My batches</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Publish assignment') }}</h2>
        <form method="POST" action="{{ route('teacher.batches.assignments.store', $batch) }}">
            @csrf
            <label>{{ __('Title') }}<input name="title" maxlength="160" required></label>
            <label>{{ __('Instructions') }}<textarea name="instructions" maxlength="10000" required></textarea></label>
            <label>{{ __('Due date') }}<input type="datetime-local" name="due_at" required></label>
            <label>{{ __('Maximum marks') }}<input type="number" name="max_marks" min="1" max="10000" required></label>
            <label style="display:flex;gap:8px;align-items:center;margin:12px 0;"><input type="checkbox" name="allow_late" value="1"> {{ __('Allow late submissions after the deadline') }}</label>
            <button type="submit" class="btn primary">{{ __('Publish') }}</button>
        </form>
    </div>

    <div class="course-list">
        @forelse($assignments as $assignment)
            <article class="course-item">
                <span class="badge info">{{ $assignment->submissions_count }} submissions</span>
                <h2 style="margin:10px 0 6px;">{{ $assignment->title }}</h2>
                <p>{{ $assignment->instructions }}</p>
                <div class="muted">Due {{ $assignment->due_at->format('M j, Y H:i') }} · {{ $assignment->max_marks }} marks</div>
                @foreach($assignment->submissions as $submission)
                    <div class="course-item" style="margin-top:12px;">
                        <strong>{{ $submission->student->name }}</strong>
                        <p>{{ $submission->answer }}</p>
                        @if($submission->attachment_path)<a class="btn secondary" href="{{ route('assignment-submissions.attachment', $submission) }}">{{ __('Download student attachment') }}: {{ $submission->attachment_name }}</a>@endif
                        <form method="POST" action="{{ route('teacher.assignments.grade', [$assignment, $submission]) }}">
                            @csrf
                            <label>{{ __('Score') }}<input type="number" name="score" min="0" max="{{ $assignment->max_marks }}" step="0.01" value="{{ $submission->score }}" required></label>
                            <label>{{ __('Feedback') }}<textarea name="feedback">{{ $submission->feedback }}</textarea></label>
                            <button type="submit" class="btn secondary">{{ __('Save grade') }}</button>
                        </form>
                    </div>
                @endforeach
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">{{ __('No assignments have been published for this batch.') }}</p></div>
        @endforelse
    </div>
@endsection