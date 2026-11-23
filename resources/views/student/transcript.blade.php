@extends('layouts.app')

@section('title', __('Academic Transcript') . ' | ' . config('app.name'))

@section('content')
<div class="panel" style="max-width:1100px;margin:24px auto;">
    <div class="meta-row" style="justify-content:space-between;align-items:center;">
        <div>
            <span class="badge info">{{ __('Official student record') }}</span>
            <h1 style="margin:10px 0 4px;">{{ __('Academic Transcript') }}</h1>
            <p class="muted">{{ __('Student:') }} <strong>{{ $student->name }}</strong> · {{ __('Generated:') }} {{ now()->format('d M Y, h:i A') }}</p>
        </div>
        <button type="button" class="btn primary" onclick="window.print()">{{ __('Print / Save PDF') }}</button>
    </div>

    <h2>{{ __('Enrolment history') }}</h2>
    @if($enrollments->isEmpty())
        <p class="muted">{{ __('No active or completed course enrolments found.') }}</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Status') }}</th><th>{{ __('Enrolled') }}</th><th>{{ __('Completed') }}</th></tr></thead>
            <tbody>@foreach($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->course?->title ?? __('Course unavailable') }}</td>
                    <td>{{ ucfirst($enrollment->status) }}</td>
                    <td>{{ $enrollment->created_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $enrollment->completed_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif

    <h2 style="margin-top:28px;">{{ __('Learning progress by course') }}</h2>
    @if($enrollments->isEmpty())
        <p class="muted">{{ __('No course progress is available yet.') }}</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Completed lessons') }}</th><th>{{ __('Total lessons') }}</th><th>{{ __('Progress') }}</th></tr></thead>
            <tbody>@foreach($enrollments as $enrollment)
                @php
                    $totalLessons = $enrollment->course?->lessons->count() ?? 0;
                    $completedLessons = (int) ($completedLessonsByCourse[$enrollment->course_id] ?? 0);
                    $progressPercent = $totalLessons > 0 ? min(100, (int) round(($completedLessons / $totalLessons) * 100)) : 0;
                @endphp
                <tr>
                    <td>{{ $enrollment->course?->title ?? __('Course unavailable') }}</td>
                    <td>{{ $completedLessons }}</td>
                    <td>{{ $totalLessons }}</td>
                    <td>{{ $progressPercent }}%</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif

    <h2 style="margin-top:28px;">{{ __('Examination results') }}</h2>
    <p class="muted">{{ __('Average percentage across scored submitted exams:') }} {{ $examAverage === null ? '—' : number_format($examAverage, 2).'%' }}</p>
    @if($examAttempts->isEmpty())
        <p class="muted">{{ __('No submitted exam attempts found.') }}</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>{{ __('Exam') }}</th><th>{{ __('Course') }}</th><th>{{ __('Score') }}</th><th>{{ __('Maximum') }}</th><th>{{ __('Submitted') }}</th></tr></thead>
            <tbody>@foreach($examAttempts as $attempt)
                @php($maximum = (int) $attempt->maximum_score)
                <tr>
                    <td>{{ $attempt->exam->title }}</td>
                    <td>{{ $attempt->exam->batch?->course?->title ?? '—' }}</td>
                    <td>{{ $attempt->score === null ? __('Pending') : number_format((float) $attempt->score, 2) }}</td>
                    <td>{{ $maximum ?: '—' }}</td>
                    <td>{{ $attempt->submitted_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif

    <h2 style="margin-top:28px;">{{ __('Assignment results') }}</h2>
    @if($assignmentSubmissions->isEmpty())
        <p class="muted">{{ __('No graded assignments found.') }}</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>{{ __('Assignment') }}</th><th>{{ __('Course') }}</th><th>{{ __('Score') }}</th><th>{{ __('Maximum') }}</th><th>{{ __('Graded') }}</th></tr></thead>
            <tbody>@foreach($assignmentSubmissions as $submission)
                <tr>
                    <td>{{ $submission->assignment->title }}</td>
                    <td>{{ $submission->assignment->batch?->course?->title ?? '—' }}</td>
                    <td>{{ number_format((float) $submission->score, 2) }}</td>
                    <td>{{ number_format((float) $submission->assignment->max_marks, 2) }}</td>
                    <td>{{ $submission->updated_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
    <h2 style="margin-top:28px;">{{ __('Issued certificates') }}</h2>
    @if($certificates->isEmpty())
        <p class="muted">{{ __('No certificates have been issued yet.') }}</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Certificate number') }}</th><th>{{ __('Issued') }}</th><th>{{ __('View') }}</th></tr></thead>
            <tbody>@foreach($certificates as $certificate)
                <tr>
                    <td>{{ $certificate->course?->title ?? __('Course unavailable') }}</td>
                    <td>{{ $certificate->certificate_number }}</td>
                    <td>{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</td>
                    <td><a href="{{ route('certificates.show', $certificate) }}">{{ __('View certificate') }}</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
    <p class="muted" style="margin-top:24px;">{{ __('This transcript reflects records currently available in the LMS. Pending or unpublished assessments may not appear.') }}</p>
</div>
<style>
@media print {
    nav, header, footer, .sidebar, .no-print, button, .btn { display:none !important; }
    body { background:#fff !important; }
    .panel { box-shadow:none !important; border:0 !important; max-width:none !important; margin:0 !important; }
    .table { width:100%; border-collapse:collapse; }
    .table th,.table td { border:1px solid #bbb; padding:7px; }
    h1,h2 { break-after:avoid; }
}
</style>
@endsection
