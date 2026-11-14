@extends('layouts.app')

@section('title', __('Branch overview') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Branch workspace') }}</span>
            <h1 style="margin:12px 0 0;">{{ auth()->user()->branch?->name }} — {{ __('batches') }}</h1>
            <p class="muted">{{ __('You can view batches assigned to your branch only.') }}</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;"><a href="{{ route('admin.branch-workspace.students.index') }}" class="btn secondary">{{ __('Students') }}</a><a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a></div>
    </div>

    <section class="panel" style="margin-bottom:22px;">
        <h2>{{ __('Create a batch in your branch') }}</h2>
        <form method="POST" action="{{ route('admin.branch-workspace.batches.store') }}" class="grid grid-2">
            @csrf
            <label>{{ __('Batch name') }}<input name="title" maxlength="160" required></label>
            <label>{{ __('Course') }}<select name="course_id" required><option value="">{{ __('Select course') }}</option>@foreach(\App\Models\Course::where('status', 'published')->orderBy('title')->get() as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
            <label>{{ __('Teacher') }}<select name="teacher_id" required><option value="">{{ __('Select teacher') }}</option>@foreach(\App\Models\User::where('role', 'teacher')->orderBy('name')->get() as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></label>
            <label>{{ __('Capacity') }}<input type="number" name="capacity" min="1" max="1000" required></label>
            <label>{{ __('Start') }}<input type="datetime-local" name="starts_at" required></label>
            <label>{{ __('End') }}<input type="datetime-local" name="ends_at" required></label>
            <label>{{ __('Fee (BDT)') }}<input type="number" name="fee" min="0" step="0.01" value="0" required></label>
            <div><button class="btn primary" type="submit">{{ __('Create batch') }}</button></div>
        </form>
    </section>
    <div class="course-list">
        @forelse($batches as $batch)
            <article class="course-item">
                <span class="badge {{ $batch->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($batch->status) }}</span>
                <h2 style="margin:10px 0 6px;">{{ $batch->title }}</h2>
                <p class="muted">{{ __('Course:') }} {{ $batch->course->title }}</p>
                <p class="muted">{{ __('Teacher:') }} {{ $batch->teacher->name }}</p>
                <p class="muted">{{ __('Students enrolled:') }} {{ $batch->enrollments_count }} / {{ $batch->capacity }}</p>
                <p class="muted">{{ $batch->starts_at->format('d M Y H:i') }} – {{ $batch->ends_at->format('d M Y H:i') }} · Fee ৳{{ number_format((float) $batch->fee, 2) }}</p>
                <details style="margin-top:12px;">
                    <summary>{{ __('Manage this batch') }}</summary>
                    <form method="POST" action="{{ route('admin.branch-workspace.batches.update', $batch) }}" class="grid grid-2" style="margin-top:12px;">
                        @csrf
                        @method('PUT')
                        <label>{{ __('Batch name') }}<input name="title" value="{{ $batch->title }}" maxlength="160" required></label>
                        <label>{{ __('Capacity') }}<input type="number" name="capacity" min="{{ $batch->enrollments_count }}" max="1000" value="{{ $batch->capacity }}" required></label>
                        <label>{{ __('Start') }}<input type="datetime-local" name="starts_at" value="{{ $batch->starts_at->format('Y-m-d\\TH:i') }}" required></label>
                        <label>{{ __('End') }}<input type="datetime-local" name="ends_at" value="{{ $batch->ends_at->format('Y-m-d\\TH:i') }}" required></label>
                        <label>{{ __('Fee (BDT)') }}<input type="number" name="fee" min="0" step="0.01" value="{{ $batch->fee }}" required></label>
                        <label>{{ __('Status') }}<select name="status"><option value="open" @selected($batch->status === 'open')>{{ __('Open') }}</option><option value="closed" @selected($batch->status === 'closed')>{{ __('Closed') }}</option><option value="completed" @selected($batch->status === 'completed')>{{ __('Completed') }}</option></select></label>
                        <div><button class="btn primary" type="submit">{{ __('Save batch') }}</button></div>
                    </form>
                </details>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No batches are assigned to this branch yet.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:18px;">{{ $batches->links() }}</div>
@endsection
