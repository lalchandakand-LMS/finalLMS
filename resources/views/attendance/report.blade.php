@extends('layouts.app')

@section('title', __('Attendance reports') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Attendance') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Attendance reports') }}</h1>
            <p class="muted">{{ __('Filter attendance by academic level, branch, course, batch and date.') }}</p>
        </div>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <form method="GET" action="{{ url()->current() }}">
            <div class="form-grid">
                <div>
                    <label for="academic-level">{{ __('Academic Level') }}</label>
                    <select id="academic-level" name="academic_level_id">
                        <option value="">{{ __('All levels') }}</option>
                        @foreach($academicLevels as $level)
                            <option value="{{ $level->id }}" @selected(($filters['academic_level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if(auth()->user()->isAdmin())
                    <div>
                        <label for="branch">{{ __('Branch') }}</label>
                        <select id="branch" name="branch_id">
                            <option value="">{{ __('All branches') }}</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="course">{{ __('Course') }}</label>
                    <select id="course" name="course_id">
                        <option value="">{{ __('All courses') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}" @selected(($filters['course_id'] ?? '') == $course->id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="batch">{{ __('Batch') }}</label>
                    <select id="batch" name="batch_id">
                        <option value="">{{ __('All batches') }}</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" @selected(($filters['batch_id'] ?? '') == $batch->id)>{{ $batch->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date-from">{{ __('From date') }}</label>
                    <input id="date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div>
                    <label for="date-to">{{ __('To date') }}</label>
                    <input id="date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="submit" class="btn primary">{{ __('Apply filters') }}</button>
                <a href="{{ url()->current() }}" class="btn secondary">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    <div class="stats-grid" style="margin-bottom:20px;">
        @foreach(['present' => __('Present'), 'absent' => __('Absent'), 'late' => __('Late'), 'excused' => __('Excused')] as $status => $label)
            <div class="stat-card">
                <span class="muted">{{ $label }}</span>
                <strong>{{ (int) ($summary[$status] ?? 0) }}</strong>
            </div>
        @endforeach
    </div>

    <div class="panel">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Student') }}</th>
                        <th>{{ __('Academic Level') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Course') }}</th>
                        <th>{{ __('Batch') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendanceRecords as $record)
                        <tr>
                            <td>{{ $record->attendance_date->format('Y-m-d') }}</td>
                            <td>{{ $record->student?->name }}</td>
                            <td>{{ $record->batch?->course?->academicLevel?->name ?? '—' }}</td>
                            <td>{{ $record->batch?->branch?->name ?? '—' }}</td>
                            <td>{{ $record->batch?->course?->title ?? '—' }}</td>
                            <td>{{ $record->batch?->title ?? '—' }}</td>
                            <td><span class="badge {{ $record->status === 'present' ? 'success' : 'secondary' }}">{{ __(ucfirst($record->status)) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">{{ __('No attendance records match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $attendanceRecords->links() }}</div>
    </div>
@endsection
