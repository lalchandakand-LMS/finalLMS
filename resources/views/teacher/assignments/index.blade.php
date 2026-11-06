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
        <h2 style="margin-top:0;">Publish assignment</h2>
        <form method="POST" action="{{ route('teacher.batches.assignments.store', $batch) }}">
            @csrf
            <label>Title<input name="title" maxlength="160" required></label>
            <label>Instructions<textarea name="instructions" maxlength="10000" required></textarea></label>
            <label>Due date<input type="datetime-local" name="due_at" required></label>
            <label>Maximum marks<input type="number" name="max_marks" min="1" max="10000" required></label>
            <button type="submit" class="btn primary">Publish</button>
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
                        <form method="POST" action="{{ route('teacher.assignments.grade', [$assignment, $submission]) }}">
                            @csrf
                            <label>Score<input type="number" name="score" min="0" max="{{ $assignment->max_marks }}" step="0.01" value="{{ $submission->score }}" required></label>
                            <label>Feedback<textarea name="feedback">{{ $submission->feedback }}</textarea></label>
                            <button type="submit" class="btn secondary">Save grade</button>
                        </form>
                    </div>
                @endforeach
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">No assignments have been published for this batch.</p></div>
        @endforelse
    </div>
@endsection