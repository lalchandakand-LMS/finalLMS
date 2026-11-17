@extends('layouts.app')

@section('title', $portalTitle)

@section('content')
@php
    $baseRoute = match(auth()->user()->role) {
        'admin' => 'admin.student-tracking',
        'branch_manager' => 'branch.student-tracking',
        'teacher' => 'teacher.student-tracking',
        'guardian' => 'guardian.student-tracking',
        default => 'student.tracking',
    };
@endphp
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Student insights') }}</span>
        <h1>{{ $portalTitle }}</h1>
        <p class="muted">Attendance, course participation and learning progress from the LMS records.</p>
    </div>
</div>
@if($canConfigureRisk)
    <p style="margin:0 0 16px;"><a class="btn secondary" href="{{ route('admin.student-tracking.settings.index') }}">{{ __('Configure risk rules') }}</a></p>
@endif

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:0 0 20px;">
    @php
        $metrics = [
            ['label' => __('Students in scope'), 'value' => $summary['students']],
            ['label' => __('Needs attention on this page'), 'value' => $summary['needs_attention']],
        ];
        if ($courseTrackingAvailable) $metrics[] = ['label' => __('Active enrollments'), 'value' => $summary['active_enrollments']];
        if ($attendanceTrackingAvailable) $metrics[] = ['label' => __('Attendance records today'), 'value' => $summary['attendance_today']];
    @endphp
    @foreach($metrics as $metric)
        <div class="panel" style="margin:0;">
            <div class="muted">{{ $metric['label'] }}</div>
            <div style="font-size:1.7rem;font-weight:800;margin-top:5px;">{{ number_format($metric['value']) }}</div>
        </div>
    @endforeach
</div>

<div class="panel">
    <form method="GET" action="{{ route($baseRoute.'.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:18px;">
        <label style="flex:1;min-width:220px;">{{ __('Search student') }}
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Name or email') }}">
        </label>
        @if($branches->isNotEmpty())
            <label>{{ __('Branch') }}
                <select name="branch_id">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string)$filters['branch_id'] === (string)$branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if($courseTrackingAvailable)
        <label>{{ __('Batch ID') }}
            <input type="number" min="1" name="batch_id" value="{{ $filters['batch_id'] }}" style="width:110px;">
        </label>
        <label>{{ __('Course ID') }}
            <input type="number" min="1" name="course_id" value="{{ $filters['course_id'] }}" style="width:110px;">
        </label>
        @if($academicLevels->isNotEmpty())
            <label>{{ __('Academic Level') }}
                <select name="academic_level_id">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach($academicLevels as $level)
                        <option value="{{ $level->id }}" @selected((int) $filters['academic_level_id'] === (int) $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>{{ __('Enrollment status') }}
            <select name="status">
                <option value="">{{ __('All statuses') }}</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __($status) }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <label>{{ __('Activity from') }}
            <input type="date" name="from_date" value="{{ $filters['from_date'] }}">
        </label>
        <label>{{ __('Activity to') }}
            <input type="date" name="to_date" value="{{ $filters['to_date'] }}">
        </label>
        <button class="btn primary" type="submit">{{ __('Apply filters') }}</button>
        <a class="btn secondary" href="{{ route($baseRoute.'.export', request()->query()) }}">{{ __('Export CSV') }}</a>
        <a class="btn secondary" href="{{ route($baseRoute.'.index') }}">{{ __('Reset') }}</a>
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:760px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid var(--border);">
                    <th style="padding:11px;">{{ __('Student') }}</th>
                    @if($courseTrackingAvailable)<th style="padding:11px;">{{ __('Active courses') }}</th>@endif
                    @if($attendanceTrackingAvailable)<th style="padding:11px;">{{ __('Attendance (:from – :to)', ['from' => $filters['from_date'], 'to' => $filters['to_date']]) }}</th>@endif
                    @if($learningTrackingAvailable)
                        <th style="padding:11px;">{{ __('Completed lessons') }}</th>
                        <th style="padding:11px;">{{ __('Last learning activity') }}</th>
                    @endif
                    <th style="padding:11px;">{{ __('Status') }}</th>
                    <th style="padding:11px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 11px;">
                            <strong>{{ $student->name }}</strong>
                            <div class="muted" style="font-size:.82rem;">{{ $student->email }}</div>
                        </td>
                        @if($courseTrackingAvailable)
                            <td style="padding:12px 11px;">{{ $student->active_enrollments_count }}</td>
                        @endif
                        @if($attendanceTrackingAvailable)
                            <td style="padding:12px 11px;">
                                @if($student->attendance_rate === null)
                                    <span class="muted">{{ __('No records') }}</span>
                                @else
                                    <strong>{{ number_format($student->attendance_rate, 1) }}%</strong>
                                    <div class="muted" style="font-size:.8rem;">{{ $student->attendance_attended }}/{{ $student->attendance_total }} {{ __('present or late') }}</div>
                                @endif
                            </td>
                        @endif
                        @if($learningTrackingAvailable)
                            <td style="padding:12px 11px;">{{ (int)$student->completed_lessons + (int)$student->completed_content }}</td>
                            <td style="padding:12px 11px;">
                                @if($student->tracking_last_activity_at)
                                    {{ \\Illuminate\\Support\\Carbon::parse($student->tracking_last_activity_at)->diffForHumans() }}
                                @else
                                    <span class="muted">{{ __('No activity recorded') }}</span>
                                @endif
                            </td>
                        @endif
                        <td style="padding:12px 11px;">
                            @if($student->tracking_needs_attention)
                                <span class="badge warning">{{ __('Needs attention') }}</span>
                                <ul class="muted" style="font-size:.78rem;margin:6px 0 0;padding-left:16px;">
                                    @foreach($student->tracking_risk_reasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="badge success">{{ __('On track') }}</span>
                            @endif
                        </td>
                        <td style="padding:12px 11px;text-align:right;">
                            <a class="btn secondary" href="{{ route($baseRoute.'.show', $student->id) }}">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + ($courseTrackingAvailable ? 1 : 0) + ($attendanceTrackingAvailable ? 1 : 0) + ($learningTrackingAvailable ? 2 : 0) }}" class="muted" style="padding:24px;text-align:center;">{{ __('No students found in your permitted scope.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px;">{{ $students->links() }}</div>
</div>
<p class="muted" style="font-size:.85rem;margin-top:12px;">{{ __('Attendance and completion totals use the selected date range; learning inactivity uses the latest activity across all dates.') }} {{ __('A student is flagged below :percent% attendance after :count records, or after :days days without recorded learning activity.', ['percent' => $riskRules['low_attendance_percent'], 'count' => $riskRules['minimum_attendance_records'], 'days' => $riskRules['inactive_days']]) }} {{ __('Flags are review signals, not automated disciplinary decisions. GPS or background location is not collected.') }}</p>
@endsection
