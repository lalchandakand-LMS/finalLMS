@extends('layouts.app')

@section('title', 'My batches | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Teacher portal</span>
            <h1 style="margin:12px 0 0;">My assigned batches</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Dashboard</a>
    </div>

    <div class="course-list">
        @forelse($batches as $batch)
            <article class="course-item">
                <span class="badge {{ $batch->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($batch->status) }}</span>
                <h2 style="margin:10px 0 6px;">{{ $batch->title }}</h2>
                <p class="muted">{{ $batch->course->title }} · {{ $batch->branch->name }}</p>
                <p class="muted">{{ $batch->starts_at->format('M j, Y H:i') }} to {{ $batch->ends_at->format('M j, Y H:i') }}</p>
                <a href="{{ route('teacher.batches.attendance.index', $batch) }}" class="btn secondary">Take attendance</a>
                <a href="{{ route('teacher.batches.assignments.index', $batch) }}" class="btn secondary">Assignments</a>
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">No batches are assigned to your account.</p></div>
        @endforelse
    </div>
@endsection