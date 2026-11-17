@extends('layouts.app')

@section('title', $student->name.' — '.__('Student Tracking'))

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
        <a href="{{ route($baseRoute.'.index') }}" class="muted">← {{ __('Back to tracking') }}</a>
        <h1 style="margin-top:8px;">{{ $student->name }}</h1>
        <p class="muted">{{ $student->email }} · {{ $student->phone_number ?: __('No phone on file') }}</p>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px;">
    <div class="panel" style="margin:0;"><div class="muted">{{ __('Active enrollments') }}</div><div style="font-size:1.6rem;font-weight:800;">{{ $enrollments->where('status','active')->count() }}</div></div>
    <div class="panel" style="margin:0;"><div class="muted">{{ __('Attendance (latest 30)') }}</div><div style="font-size:1.6rem;font-weight:800;">{{ $attendanceRate === null ? '—' : number_format($attendanceRate,1).'%' }}</div></div>
    <div class="panel" style="margin:0;"><div class="muted">{{ __('Completed lessons') }}</div><div style="font-size:1.6rem;font-weight:800;">{{ $completedLessonCount + $completedContentCount }}</div></div>
</div>

<div class="panel">
    <h2>{{ __('Learning risk review') }}</h2>
    @forelse($riskFlags as $flag)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <span class="badge warning">{{ match($flag['code']) {
                'low_attendance' => __('Low attendance'),
                'inactive_learning' => __('Learning inactivity'),
                'overdue_assignments' => __('Overdue assignments'),
                'consecutive_absences' => __('Consecutive absences'),
                'declining_scores' => __('Declining scores'),
                default => __('Learning review'),
            } }}</span>
            <p style="margin:8px 0;">{{ $flag['reason'] }}</p>
            @if($canManageRisk)
                <form method="POST" action="{{ route($baseRoute.'.risk-actions.store', $student->id) }}" style="display:grid;gap:8px;">
                    @csrf
                    <input type="hidden" name="risk_code" value="{{ $flag['code'] }}">
                    <label>{{ __('Follow-up note') }}
                        <textarea name="note" rows="2" maxlength="1000" required placeholder="{{ __('Record the follow-up reason or action') }}"></textarea>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn secondary" type="submit" name="action" value="note">{{ __('Save note') }}</button>
                        <button class="btn primary" type="submit" name="action" value="dismiss">{{ __('Dismiss this flag') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('No active risk flags in the current review window.') }}</p>
    @endforelse

    @if($canManageRisk && count($dismissedRiskFlags))
        <h3 style="margin-top:16px;">{{ __('Dismissed flags') }}</h3>
        @foreach($dismissedRiskFlags as $flag)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                <span>{{ $flag['reason'] }}</span>
                <form method="POST" action="{{ route($baseRoute.'.risk-actions.store', $student->id) }}" style="margin-top:8px;">
                    @csrf
                    <input type="hidden" name="risk_code" value="{{ $flag['code'] }}">
                    <input type="hidden" name="action" value="reopen">
                    <button class="btn secondary" type="submit">{{ __('Reopen flag') }}</button>
                </form>
            </div>
        @endforeach
    @endif

    @if($canManageRisk && $riskActions->isNotEmpty())
        <h3 style="margin-top:18px;">{{ __('Risk action audit history') }}</h3>
        @foreach($riskActions as $action)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                <strong>{{ __($action->action) }} · {{ str_replace('_', ' ', $action->risk_code) }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $action->actor_name }} · {{ \Illuminate\Support\Carbon::parse($action->created_at)->format('d M Y, H:i') }}</div>
                @if($action->note)
                    <p style="margin:6px 0;">{{ $action->note }}</p>
                @endif
            </div>
        @endforeach
    @endif
</div>

<div class="panel">
    <h2>{{ __('Course enrollments') }}</h2>
    @forelse($enrollments as $enrollment)
        <div style="padding:12px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $enrollment->course?->title ?? __('Course unavailable') }}</strong>
            <span class="badge {{ $enrollment->status === 'active' ? 'success' : 'secondary' }}">{{ __($enrollment->status) }}</span>
            <div class="muted" style="font-size:.85rem;">{{ $enrollment->batch?->title ?? __('No batch assigned') }} · {{ $enrollment->batch?->branch?->name ?? __('Branch not assigned') }}</div>
        </div>
    @empty
        <p class="muted">{{ __('No course enrollments found.') }}</p>
    @endforelse
</div>

<div class="panel">
    <h2>{{ __('Attendance history') }}</h2>
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:520px;border-collapse:collapse;">
            <thead><tr style="text-align:left;"><th style="padding:9px;">{{ __('Date') }}</th><th style="padding:9px;">{{ __('Batch') }}</th><th style="padding:9px;">{{ __('Course') }}</th><th style="padding:9px;">{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse($attendanceRecords as $record)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:9px;">{{ $record->attendance_date?->format('d M Y') }}</td>
                    <td style="padding:9px;">{{ $record->batch?->title ?? '—' }}</td>
                    <td style="padding:9px;">{{ $record->batch?->course?->title ?? '—' }}</td>
                    <td style="padding:9px;"><span class="badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ __($record->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="padding:16px;">{{ __('No attendance records found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    <div class="panel">
        <h2>{{ __('Lesson progress') }}</h2>
        @forelse($lessonProgress as $progress)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $progress->lesson?->title ?? __('Lesson unavailable') }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $progress->lesson?->course?->title ?? __('Course') }} · {{ $progress->completed_at ? __('Completed :time', ['time' => $progress->completed_at->diffForHumans()]) : __('In progress') }}</div>
            </div>
        @empty
            <p class="muted">{{ __('No legacy lesson progress recorded.') }}</p>
        @endforelse
    </div>
    <div class="panel">
        <h2>{{ __('Course Content Studio progress') }}</h2>
        @forelse($contentProgress as $progress)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $progress->curriculumItem?->post?->title ?? $progress->curriculumItem?->section_title ?? __('Course content') }}</strong>
                <div class="muted" style="font-size:.82rem;">{{ $progress->curriculumItem?->course?->title ?? __('Course') }} · {{ $progress->completed_at ? __('Completed :time', ['time' => $progress->completed_at->diffForHumans()]) : __('In progress') }}</div>
            </div>
        @empty
            <p class="muted">{{ __('No Course Content Studio progress recorded.') }}</p>
        @endforelse
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    @if($noticeTrackingAvailable)
    <div class="panel">
        <h2>{{ __('Student-visible notices') }}</h2>
        @forelse($announcements as $announcement)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <strong>{{ $announcement->title }}</strong>
                    <span class="badge {{ $announcement->reads->isNotEmpty() ? 'success' : 'secondary' }}">{{ $announcement->reads->isNotEmpty() ? __('Read') : __('Unread') }}</span>
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
            <p class="muted">{{ __('No active student-visible notices in this student\'s branch/course scope.') }}</p>
        @endforelse
    </div>
    @endif
    @if($supportTrackingAvailable)
        <div class="panel">
            <h2>{{ __('Support engagement (Admin only)') }}</h2>
            @forelse($supportTickets as $ticket)
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <strong>{{ $ticket->subject }}</strong>
                    <span class="badge {{ $ticket->status === 'closed' ? 'secondary' : 'info' }}">{{ __($ticket->status) }}</span>
                    <div class="muted" style="font-size:.82rem;">{{ $ticket->branch?->name ?? __('No branch') }} · {{ __('Last activity :time', ['time' => $ticket->last_message_at?->diffForHumans() ?? __('Not recorded')]) }}</div>
                    <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="btn secondary" style="margin-top:6px;">{{ __('Open support ticket') }}</a>
                </div>
            @empty
                <p class="muted">{{ __('No support tickets are recorded for this student.') }}</p>
            @endforelse
        </div>
    @endif
</div>

<div class="panel">
    <h2>{{ __('Overdue assignments') }}</h2>
    @forelse($overdueAssignments as $assignment)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $assignment->title }}</strong>
            <div class="muted" style="font-size:.82rem;">{{ $assignment->batch?->title ?? __('Assigned batch') }} · {{ __('Due :time', ['time' => $assignment->due_at?->format('d M Y, H:i')]) }}</div>
        </div>
    @empty
        <p class="muted">{{ __('No overdue assignments without a submission in your permitted batch scope.') }}</p>
    @endforelse
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
    <div class="panel">
        <h2>{{ __('Recent assessment results') }}</h2>
        @forelse($examAttempts as $attempt)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $attempt->exam?->title ?? __('Assessment') }}</strong>
                <div class="muted" style="font-size:.82rem;">
                    {{ __($attempt->status) }}
                    · {{ __('Score: :score', ['score' => $attempt->score === null ? __('Not graded') : $attempt->score]) }}
                    · {{ $attempt->submitted_at?->format('d M Y, H:i') ?? __('Not submitted') }}
                </div>
            </div>
        @empty
            <p class="muted">{{ __('No assessment attempts in your permitted course scope.') }}</p>
        @endforelse
    </div>
    <div class="panel">
        <h2>{{ __('Assignment submissions') }}</h2>
        @forelse($assignmentSubmissions as $submission)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $submission->assignment?->title ?? __('Assignment') }}</strong>
                <div class="muted" style="font-size:.82rem;">
                    {{ __($submission->status) }}
                    · {{ __('Score: :score', ['score' => $submission->score === null ? __('Not graded') : $submission->score]) }}
                    · {{ __('Submitted: :time', ['time' => $submission->submitted_at?->format('d M Y, H:i') ?? __('Not submitted')]) }}
                </div>
                @if($submission->assignment?->due_at)
                    <div class="muted" style="font-size:.78rem;">{{ __('Due :time', ['time' => $submission->assignment->due_at->format('d M Y, H:i')]) }}</div>
                @endif
            </div>
        @empty
            <p class="muted">{{ __('No assignment submissions in your permitted batch scope.') }}</p>
        @endforelse
    </div>
</div>
@endsection
