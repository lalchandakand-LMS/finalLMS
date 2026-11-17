@extends('layouts.app')

@section('title', __('Student Tracking Risk Rules'))

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.student-tracking.index') }}" class="muted">← {{ __('Back to tracking') }}</a>
        <h1 style="margin-top:8px;">{{ __('Student Tracking Risk Rules') }}</h1>
        <p class="muted">{{ __('Configure transparent review signals. These rules never trigger disciplinary or automated account actions.') }}</p>
    </div>
</div>

<div class="panel" style="max-width:760px;">
    <form method="POST" action="{{ route('admin.student-tracking.settings.update') }}">
        @csrf
        @method('PUT')
        <label style="display:block;margin-bottom:16px;">
            {{ __('Low attendance threshold (%)') }}
            <input type="number" name="low_attendance_percent" min="1" max="100" required
                value="{{ old('low_attendance_percent', $lowAttendancePercent) }}">
            <span class="muted" style="font-size:.82rem;">{{ __('Flag attendance below this percentage.') }}</span>
        </label>
        <label style="display:block;margin-bottom:16px;">
            {{ __('Minimum attendance records') }}
            <input type="number" name="minimum_attendance_records" min="1" max="30" required
                value="{{ old('minimum_attendance_records', $minimumAttendanceRecords) }}">
            <span class="muted" style="font-size:.82rem;">{{ __('Do not calculate a low-attendance flag until this many records exist.') }}</span>
        </label>
        <label style="display:block;margin-bottom:16px;">
            {{ __('Learning inactivity threshold (days)') }}
            <input type="number" name="inactive_days" min="1" max="365" required
                value="{{ old('inactive_days', $inactiveDays) }}">
            <span class="muted" style="font-size:.82rem;">{{ __('Flag students with no recorded LMS learning activity for this many days.') }}</span>
        </label>
        <button type="submit" class="btn primary">{{ __('Save risk rules') }}</button>
    </form>
</div>
@endsection
