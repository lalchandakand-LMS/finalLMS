@extends('layouts.app')

@section('title', 'Attendance | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $batch->course->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $batch->title }} attendance</h1>
        </div>
        <a href="{{ route('teacher.batches.index') }}" class="btn secondary">My batches</a>
    </div>

    <div class="panel">
        @if($students->isEmpty())
            <p class="muted" style="margin:0;">No students are enrolled in this batch.</p>
        @else
            <form method="POST" action="{{ route('teacher.batches.attendance.store', $batch) }}">
                @csrf
                <label for="attendance-date">Class date</label>
                <input id="attendance-date" type="date" name="attendance_date" value="{{ request('date', now()->toDateString()) }}" required>

                <div class="course-list" style="margin-top:18px;">
                    @foreach($students as $index => $enrollment)
                        <div class="course-item">
                            <input type="hidden" name="records[{{ $index }}][student_id]" value="{{ $enrollment->user_id }}">
                            <div class="meta-row" style="margin-top:0; align-items:center;">
                                <div><strong>{{ $enrollment->user->name }}</strong><div class="muted">{{ $enrollment->user->email }}</div></div>
                                <select name="records[{{ $index }}][status]" required>
                                    @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($attendance[$enrollment->user_id] ?? 'present') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn primary" style="margin-top:18px;">Save attendance</button>
            </form>
        @endif
    </div>
@endsection