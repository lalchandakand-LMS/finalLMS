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
    $canManageRisk = in_array(auth()->user()->role, ['admin', 'branch_manager', 'teacher'], true);
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
    <h2>Learning risk review</h2>
    @forelse($riskFlags as $flag)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <span class="badge warning">{{ match($flag['code']) {
                'low_attendance' => 'Low attendance',
                'inactive_learning' => 'Learning inactivity',
                'overdue_assignments' => 'Overdue assignments',
                'consecutive_absences' => 'Consecutive absences',
                'declining_scores' => 'Declining scores',
                default => 'Learning review',
            } }}</span>
            <p style="margin:8px 0;">{{ $flag['reason'] }}</p>
            @if($canManageRisk)
                <form method="POST" action="{{ route($baseRoute.'.risk-actions.store', $student->id) }}" style="display:grid;gap:8px;">
                    @csrf
                    <input type="hidden" name="risk_code" value="{{ $flag['code'] }}">
                    <label>Follow-up note
                        <textarea name="note" rows="2" maxlength="1000" required placeholder="Record the follow-up reason or action"></textarea>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn secondary" type="submit" name="action" value="note">Save note</button>
                        <button class="btn primary" type="submit" name="action" value="dismiss">Dismiss this flag</button>
                    </div>
                </form>
            @endif
        </div>
    @empty
        <p class="muted">No active risk flags in the current review window.</p>
    @endforelse

    @if($canManageRisk && count($dismissedRiskFlags))
        <h3 style="margin-top:16px;">Dismissed flags</h3>
        @foreach($dismissedRiskFlags as $flag)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                <span>{{ $flag['reason'] }}</span>
                <form method="POST" action="{{ route($baseRoute.'.risk-actions.store', $student->id) }}" style="margin-top:8px;">
                    @csrf
                    <input type="hidden" name="risk_code" value="{{ $flag['code'] }}">
                    <input type="hidden" name="action" value="reopen">
                    <button class="btn secondary" type="submit">Reopen flag</button>
                </form>
            </div>
        @endforeach
    @endif

    @if($canManageRisk && $riskActions->isNotEmpty())
        <h3 style="margin-top:18px;">Risk action audit history</h3>
        @foreach($riskActions as $action)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                <strong>{{ ucfirst($action->action) }} · {{ str_replace('_', ' ', $action->risk_code) }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $action->actor_name }} · {{ \Illuminate\Support\Carbon::parse($action->created_at)->format('d M Y, H:i') }}</div>
                @if($action->note)
                    <p style="margin:6px 0;">{{ $action->note }}</p>
                @endif
            </div>
        @endforeach
    @endif
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

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    <div class="panel">
        <h2>Student-visible notices</h2>
        @forelse($announcements as $announcement)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <strong>{{ $announcement->title }}</strong>
                    <span class="badge {{ $announcement->reads->isNotEmpty() ? 'success' : 'secondary' }}">{{ $announcement->reads->isNotEmpty() ? 'Read' : 'Unread' }}</span>
                </div>
                @if($announcement->course)
                    <div class="muted" style="font-size:.82rem;">{{ $announcement->course->title }}</div>
                @endif
                @if($announcement->body)
                    <p style="margin:6px 0;">{{ \Illuminate\Support\Str::limit(strip_tags($announcement->body), 240) }}</p>
                @endif
                <div class="muted" style="font-size:.78rem;">{{ $announcement->created_at?->format('d M Y, H:i') }}</div>
            </div>
        @empty
            <p class="muted">No active student-visible notices in this student's branch/course scope.</p>
        @endforelse
    </div>
    @if(auth()->user()->role === 'admin')
        <div class="panel">
            <h2>Support engagement (Admin only)</h2>
            @forelse($supportTickets as $ticket)
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <strong>{{ $ticket->subject }}</strong>
                    <span class="badge {{ $ticket->status === 'closed' ? 'secondary' : 'info' }}">{{ ucfirst($ticket->status) }}</span>
                    <div class="muted" style="font-size:.82rem;">{{ $ticket->branch?->name ?? 'No branch' }} · Last activity {{ $ticket->last_message_at?->diffForHumans() ?? 'Not recorded' }}</div>
                    <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="btn secondary" style="margin-top:6px;">Open support ticket</a>
                </div>
            @empty
                <p class="muted">No support tickets are recorded for this student.</p>
            @endforelse
        </div>
    @endif
</div>

<div class="panel">
    <h2>Overdue assignments</h2>
    @forelse($overdueAssignments as $assignment)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $assignment->title }}</strong>
            <div class="muted" style="font-size:.82rem;">{{ $assignment->batch?->title ?? 'Assigned batch' }} · Due {{ $assignment->due_at?->format('d M Y, H:i') }}</div>
        </div>
    @empty
        <p class="muted">No overdue assignments without a submission in your permitted batch scope.</p>
    @endforelse
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    <div class="panel">
        <h2>Recent assessment results</h2>
        @forelse($examAttempts as $attempt)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $attempt->exam?->title ?? 'Assessment' }}</strong>
                <div class="muted" style="font-size:.82rem;">
                    {{ ucfirst($attempt->status) }}
                    · Score: {{ $attempt->score === null ? 'Not graded' : $attempt->score }}
                    · {{ $attempt->submitted_at?->format('d M Y, H:i') ?? 'Not submitted' }}
                </div>
            </div>
        @empty
            <p class="muted">No assessment attempts in your permitted course scope.</p>
        @endforelse
    </div>
    <div class="panel">
        <h2>Assignment submissions</h2>
        @forelse($assignmentSubmissions as $submission)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $submission->assignment?->title ?? 'Assignment' }}</strong>
                <div class="muted" style="font-size:.82rem;">
                    {{ ucfirst($submission->status) }}
                    · Score: {{ $submission->score === null ? 'Not graded' : $submission->score }}
                    · Submitted: {{ $submission->submitted_at?->format('d M Y, H:i') ?? 'Not submitted' }}
                </div>
                @if($submission->assignment?->due_at)
                    <div class="muted" style="font-size:.78rem;">Due {{ $submission->assignment->due_at->format('d M Y, H:i') }}</div>
                @endif
            </div>
        @empty
            <p class="muted">No assignment submissions in your permitted batch scope.</p>
        @endforelse
    </div>
</div>
@endsection
