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

    <div class="course-list">
        @forelse($batches as $batch)
            <article class="course-item">
                <span class="badge {{ $batch->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($batch->status) }}</span>
                <h2 style="margin:10px 0 6px;">{{ $batch->title }}</h2>
                <p class="muted">Course: {{ $batch->course->title }}</p>
                <p class="muted">Teacher: {{ $batch->teacher->name }}</p>
                <p class="muted">Students enrolled: {{ $batch->enrollments_count }} / {{ $batch->capacity }}</p>
                <p class="muted">{{ $batch->starts_at->format('d M Y H:i') }} – {{ $batch->ends_at->format('d M Y H:i') }}</p>
            </article>
        @empty
            <div class="panel"><p class="muted">No batches are assigned to this branch yet.</p></div>
        @endforelse
    </div>
    <div style="margin-top:18px;">{{ $batches->links() }}</div>
@endsection
