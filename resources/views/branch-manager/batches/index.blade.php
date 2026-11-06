@extends('layouts.app')

@section('title', 'Branch overview | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Branch manager</span>
            <h1 style="margin:12px 0 0;">{{ auth()->user()->branch?->name }} — batches</h1>
            <p class="muted">You can view batches assigned to your branch only.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Dashboard</a>
    </div>

    <section class="panel" style="margin-bottom:22px;">
        <h2>Create a batch in your branch</h2>
        <form method="POST" action="{{ route('branch-manager.batches.store') }}" class="grid grid-2">
            @csrf
            <label>Batch name<input name="title" maxlength="160" required></label>
            <label>Course<select name="course_id" required><option value="">Select course</option>@foreach(\App\Models\Course::where('status', 'published')->orderBy('title')->get() as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
            <label>Teacher<select name="teacher_id" required><option value="">Select teacher</option>@foreach(\App\Models\User::where('role', 'teacher')->orderBy('name')->get() as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></label>
            <label>Capacity<input type="number" name="capacity" min="1" max="1000" required></label>
            <label>Start<input type="datetime-local" name="starts_at" required></label>
            <label>End<input type="datetime-local" name="ends_at" required></label>
            <label>Fee (BDT)<input type="number" name="fee" min="0" step="0.01" value="0" required></label>
            <div><button class="btn primary" type="submit">Create batch</button></div>
        </form>
    </section>
    <div class="course-list">
        @forelse($batches as $batch)
            <article class="course-item">
                <span class="badge {{ $batch->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($batch->status) }}</span>
                <h2 style="margin:10px 0 6px;">{{ $batch->title }}</h2>
                <p class="muted">Course: {{ $batch->course->title }}</p>
                <p class="muted">Teacher: {{ $batch->teacher->name }}</p>
                <p class="muted">Students enrolled: {{ $batch->enrollments_count }} / {{ $batch->capacity }}</p>
                <p class="muted">{{ $batch->starts_at->format('d M Y H:i') }} – {{ $batch->ends_at->format('d M Y H:i') }} · Fee ৳{{ number_format((float) $batch->fee, 2) }}</p>
                <details style="margin-top:12px;">
                    <summary>Manage this batch</summary>
                    <form method="POST" action="{{ route('branch-manager.batches.update', $batch) }}" class="grid grid-2" style="margin-top:12px;">
                        @csrf
                        @method('PUT')
                        <label>Batch name<input name="title" value="{{ $batch->title }}" maxlength="160" required></label>
                        <label>Capacity<input type="number" name="capacity" min="{{ $batch->enrollments_count }}" max="1000" value="{{ $batch->capacity }}" required></label>
                        <label>Start<input type="datetime-local" name="starts_at" value="{{ $batch->starts_at->format('Y-m-d\\TH:i') }}" required></label>
                        <label>End<input type="datetime-local" name="ends_at" value="{{ $batch->ends_at->format('Y-m-d\\TH:i') }}" required></label>
                        <label>Fee (BDT)<input type="number" name="fee" min="0" step="0.01" value="{{ $batch->fee }}" required></label>
                        <label>Status<select name="status"><option value="open" @selected($batch->status === 'open')>Open</option><option value="closed" @selected($batch->status === 'closed')>Closed</option><option value="completed" @selected($batch->status === 'completed')>Completed</option></select></label>
                        <div><button class="btn primary" type="submit">Save batch</button></div>
                    </form>
                </details>
            </article>
        @empty
            <div class="panel"><p class="muted">No batches are assigned to this branch yet.</p></div>
        @endforelse
    </div>
    <div style="margin-top:18px;">{{ $batches->links() }}</div>
@endsection
