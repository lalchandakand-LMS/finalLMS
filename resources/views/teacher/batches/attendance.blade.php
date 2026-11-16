@extends('layouts.app')

@section('title', 'Attendance | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $batch->course->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $batch->title }} attendance</h1>
        </div>
        <a href="{{ $attendanceBackUrl }}" class="btn secondary">{{ __('Back to batches') }}</a>
    </div>

    <div class="panel">
        @if($students->isEmpty())
            <p class="muted" style="margin:0;">{{ __('No students are enrolled in this batch.') }}</p>
        @else
            <form id="attendance-form" method="POST" action="{{ $attendanceStoreRoute }}">
                @csrf
                <label for="attendance-date">{{ __('Class date') }}</label>
                <input id="attendance-date" type="date" name="attendance_date" value="{{ request('date', now()->toDateString()) }}" required>

                <input type="hidden" name="latitude" id="attendance-latitude" required>
                <input type="hidden" name="longitude" id="attendance-longitude" required>
                <input type="hidden" name="location_accuracy_m" id="attendance-location-accuracy" required>
                <input type="hidden" name="location_captured_at" id="attendance-location-captured-at" required>
                <p class="muted" role="note">{{ __('Location permission is required when saving attendance. Location is captured only for this action; background tracking is not used.') }}</p>

                <div class="course-list" style="margin-top:18px;">
                    @foreach($students as $index => $enrollment)
                        <div class="course-item">
                            <input type="hidden" name="records[{{ $index }}][student_id]" value="{{ $enrollment->user_id }}">
                            <div class="meta-row" style="margin-top:0; align-items:center;">
                                <div><strong>{{ $enrollment->user->name }}</strong><div class="muted">{{ $enrollment->user->email }}</div></div>
                                <select name="records[{{ $index }}][status]" required>
                                    @php($options = ['present' => __('Present'), 'absent' => __('Absent'), 'late' => __('Late')])
                                    @if(config('attendance.allow_excused', false))
                                        @php($options['excused'] = __('Excused'))
                                    @endif
                                    @foreach($options as $value => $label)
                                        <option value="{{ $value }}" @selected(($attendance[$enrollment->user_id] ?? 'present') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if(isset($attendance[$enrollment->user_id]))
                                <label for="attendance-reason-{{ $index }}">{{ __('Correction reason (required if changing status)') }}</label>
                                <input id="attendance-reason-{{ $index }}" type="text" name="records[{{ $index }}][reason]" maxlength="500" placeholder="{{ __('Explain why this attendance is being corrected') }}">
                            @endif
                        </div>
                    @endforeach
                </div>
                <button id="attendance-submit" type="submit" class="btn primary" style="margin-top:18px;">{{ __('Save attendance') }}</button>
            </form>
        @endif
    </div>

    <div class="panel" style="margin-top:20px;">
        <h2>{{ __('Attendance audit history') }}</h2>
        @if($auditHistory->isEmpty())
            <p class="muted">{{ __('No changes have been recorded for this class date.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('Previous') }}</th>
                            <th>{{ __('New') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Changed by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditHistory as $audit)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $audit->student_name }}</td>
                                <td>{{ __(ucwords(str_replace('_', ' ', $audit->action))) }}</td>
                                <td>{{ $audit->old_status ? __(ucfirst($audit->old_status)) : '—' }}</td>
                                <td>{{ $audit->new_status ? __(ucfirst($audit->new_status)) : '—' }}</td>
                                <td>{{ $audit->reason ?: '—' }}</td>
                                <td>{{ $audit->actor_name ?: __('Deleted user') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('attendance-form');
    if (!form) return;

    let locationReady = false;
    form.addEventListener('submit', (event) => {
        if (locationReady) return;
        event.preventDefault();

        if (!navigator.geolocation) {
            window.alert(@json(__('This device/browser does not support location. Attendance cannot be saved.')));
            return;
        }

        const button = document.getElementById('attendance-submit');
        button.disabled = true;
        button.textContent = @json(__('Getting location…'));

        navigator.geolocation.getCurrentPosition((position) => {
            document.getElementById('attendance-latitude').value = position.coords.latitude;
            document.getElementById('attendance-longitude').value = position.coords.longitude;
            document.getElementById('attendance-location-accuracy').value = position.coords.accuracy;
            document.getElementById('attendance-location-captured-at').value = new Date().toISOString();
            locationReady = true;
            form.requestSubmit();
        }, () => {
            button.disabled = false;
            button.textContent = @json(__('Save attendance'));
            window.alert(@json(__('Location permission is required to save attendance. Please allow location access and try again.')));
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    });
})();
</script>
@endpush
