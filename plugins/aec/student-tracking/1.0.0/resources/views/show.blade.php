@extends('layouts.app')

@section('title', $student->name.' — Student Tracking')

@section('content')
@php
    $baseRoute = match(auth()->user()->role) {
        'admin' => 'admin.student-tracking',
        'branch_manager' => 'branch.student-tracking',
        'teacher' => 'teacher.student-tracking',
        'guardian' => 'guardian.student-tracking',
        default => 'student.tracking',
    };
    $completedLessonCount = $lessonProgress->whereNotNull('completed_at')->count();
    $completedContentCount = $contentProgress->whereNotNull('completed_at')->count();
@endphp
<div class="page-header">
    <div>
        <a href="{{ route($baseRoute.'.index') }}" class="muted">← Back to tracking</a>
        <h1 style="margin-top:8px;">{{ $student->name }}</h1>
        <p class="muted">{{ $student->email }} · {{ $student->phone_number ?: 'No phone on file' }}</p>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px;">
    <div class="panel" style="margin:0;"><div class="muted">Active enrollments</div><div style="font-size:1.6rem;font-weight:800;">{{ $enrollments->where('status','active')->count() }}</div></div>
    <div class="panel" style="margin:0;"><div class="muted">Attendance (latest 30)</div><div style="font-size:1.6rem;font-weight:800;">{{ $attendanceRate === null ? '—' : number_format($attendanceRate,1).'%' }}</div></div>
    <div class="panel" style="margin:0;"><div class="muted">Completed lessons</div><div style="font-size:1.6rem;font-weight:800;">{{ $completedLessonCount + $completedContentCount }}</div></div>
</div>

<div class="panel">
    <h2>Course enrollments</h2>
    @forelse($enrollments as $enrollment)
        <div style="padding:12px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $enrollment->course?->title ?? 'Course unavailable' }}</strong>
            <span class="badge {{ $enrollment->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($enrollment->status) }}</span>
            <div class="muted" style="font-size:.85rem;">{{ $enrollment->batch?->title ?? 'No batch assigned' }} · {{ $enrollment->batch?->branch?->name ?? 'Branch not assigned' }}</div>
        </div>
    @empty
        <p class="muted">No course enrollments found.</p>
    @endforelse
</div>

<div class="panel">
    <h2>Attendance history</h2>
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:520px;border-collapse:collapse;">
            <thead><tr style="text-align:left;"><th style="padding:9px;">Date</th><th style="padding:9px;">Batch</th><th style="padding:9px;">Course</th><th style="padding:9px;">Status</th></tr></thead>
            <tbody>
            @forelse($attendanceRecords as $record)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:9px;">{{ $record->attendance_date?->format('d M Y') }}</td>
                    <td style="padding:9px;">{{ $record->batch?->title ?? '—' }}</td>
                    <td style="padding:9px;">{{ $record->batch?->course?->title ?? '—' }}</td>
                    <td style="padding:9px;"><span class="badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="padding:16px;">No attendance records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    <div class="panel">
        <h2>Lesson progress</h2>
        @forelse($lessonProgress as $progress)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $progress->lesson?->title ?? 'Lesson unavailable' }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $progress->lesson?->course?->title ?? 'Course' }} · {{ $progress->completed_at ? 'Completed '.$progress->completed_at->diffForHumans() : 'In progress' }}</div>
            </div>
        @empty
            <p class="muted">No legacy lesson progress recorded.</p>
        @endforelse
    </div>
    <div class="panel">
        <h2>Course Content Studio progress</h2>
        @forelse($contentProgress as $progress)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $progress->curriculumItem?->post?->title ?? $progress->curriculumItem?->section_title ?? 'Course content' }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $progress->curriculumItem?->course?->title ?? 'Course' }} · {{ $progress->completed_at ? 'Completed '.$progress->completed_at->diffForHumans() : 'In progress' }}</div>
            </div>
        @empty
            <p class="muted">No Course Content Studio progress recorded.</p>
        @endforelse
    </div>
</div>
@endsection
