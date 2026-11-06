@extends('layouts.app')

@section('title', 'My attendance | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Student portal</span>
            <h1 style="margin:12px 0 0;">My attendance</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Dashboard</a>
    </div>

    <div class="course-list">
        @forelse($attendanceRecords as $record)
            <article class="course-item">
                <span class="badge {{ $record->status === 'present' ? 'success' : 'warning' }}">{{ ucfirst($record->status) }}</span>
                <h2 style="margin:10px 0 6px;">{{ $record->batch->course->title }}</h2>
                <div class="muted">{{ $record->batch->title }} · {{ $record->batch->branch->name }}</div>
                <div class="muted">{{ $record->attendance_date->format('M j, Y') }}</div>
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">No attendance records are available yet.</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $attendanceRecords->links() }}</div>
@endsection