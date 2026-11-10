@extends('layouts.app')
@section('title', __('Guardian dashboard'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Guardian portal') }}</span>
        <h1>{{ __('Student progress overview') }}</h1>
        <p class="muted">{{ __('View learning, attendance, results and fee records for students formally linked to your account.') }}</p>
    </div>
    <span class="badge success">{{ __('Verified access') }}</span>
</div>

@if($links->isEmpty())
    <div class="panel">
        <h2>{{ __('No linked students yet') }}</h2>
        <p class="muted">{{ __('Your account does not have an active, administrator-verified student relationship. Please contact the academy administration to verify the relationship.') }}</p>
    </div>
@else
    <div class="panel" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('guardian.dashboard') }}" class="grid grid-2">
            <div>
                <label for="student_id">{{ __('Select student') }}</label>
                <select id="student_id" name="student_id" required>
                    @foreach($links as $link)
                        <option value="{{ $link->student_id }}" @selected($selectedLink && $selectedLink->student_id === $link->student_id)>{{ $link->student->name }} — {{ __(ucwords(str_replace('_', ' ', $link->relationship))) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;align-items:end;"><button class="btn primary" type="submit">{{ __('View student records') }}</button></div>
        </form>
    </div>

    <div class="panel" style="margin-bottom:18px;">
        <h2>{{ $student->name }}</h2>
        <p class="muted">{{ __('Verified relationship') }}: {{ __(ucwords(str_replace('_', ' ', $selectedLink->relationship))) }} · {{ __('Access verified') }} {{ $selectedLink->verified_at?->format('d M Y') }}</p>
    </div>

    @php($progressPercent = $totalLessons > 0 ? min(100, (int) round($completedLessons * 100 / $totalLessons)) : 0)
    <div class="dash-summary">
        <div class="summary-box"><span class="muted">{{ __('Enrolled courses') }}</span><strong>{{ $enrollments->count() }}</strong></div>
        <div class="summary-box"><span class="muted">{{ __('Lessons completed') }}</span><strong>{{ $completedLessons }} / {{ $totalLessons }}</strong></div>
        <div class="summary-box"><span class="muted">{{ __('Learning progress') }}</span><strong>{{ $progressPercent }}%</strong></div>
        <div class="summary-box"><span class="muted">{{ __('Attendance records') }}</span><strong>{{ $attendanceSummary->sum() }}</strong></div>
    </div>

    <div class="grid grid-2" style="margin-top:18px;">
        <div class="panel">
            <h2>{{ __('Course progress') }}</h2>
            @forelse($enrollments as $enrollment)
                <div class="course-item">
                    <strong>{{ $enrollment->course?->title ?? __('Course unavailable') }}</strong>
                    <p class="muted">{{ __('Teacher:') }} {{ $enrollment->course?->teacher?->name ?? '—' }} · {{ __('Status') }}: {{ __(ucfirst($enrollment->status)) }}</p>
                    @if($enrollment->batch)<small class="muted">{{ __('Batch') }}: {{ $enrollment->batch->title }}</small>@endif
                </div>
            @empty
                <p class="muted">{{ __('No course enrollments found for this student.') }}</p>
            @endforelse
        </div>
        <div class="panel">
            <h2>{{ __('Attendance summary') }}</h2>
            @forelse($attendanceSummary as $status => $total)
                <div class="course-item" style="display:flex;justify-content:space-between;"><span>{{ __(ucfirst($status)) }}</span><strong>{{ $total }}</strong></div>
            @empty
                <p class="muted">{{ __('No attendance records are available yet.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <h2>{{ __('Recent attendance') }}</h2>
        @forelse($attendance as $record)
            <div class="course-item" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>{{ $record->attendance_date?->format('d M Y') }} · {{ $record->batch?->course?->title ?? __('Class') }}</span>
                <strong>{{ __(ucfirst($record->status)) }}</strong>
            </div>
        @empty
            <p class="muted">{{ __('No attendance records are available yet.') }}</p>
        @endforelse
    </div>

    <div class="grid grid-2" style="margin-top:18px;">
        <div class="panel">
            <h2>{{ __('Exam results') }}</h2>
            @forelse($examAttempts as $attempt)
                <div class="course-item">
                    <strong>{{ $attempt->exam?->title ?? __('Exam') }}</strong>
                    <p class="muted">{{ __('Submitted') }}: {{ $attempt->submitted_at?->format('d M Y H:i') }}</p>
                    <strong>{{ __('Score') }}: {{ $attempt->score ?? '—' }}</strong>
                </div>
            @empty
                <p class="muted">{{ __('No submitted exam results are available yet.') }}</p>
            @endforelse
        </div>
        <div class="panel">
            <h2>{{ __('Certificates') }}</h2>
            @forelse($certificates as $certificate)
                <div class="course-item"><strong>{{ $certificate->course?->title ?? __('Course') }}</strong><p class="muted">{{ $certificate->certificate_number }}</p></div>
            @empty
                <p class="muted">{{ __('No certificates have been issued yet.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="panel" style="margin-top:18px;">
        <h2>{{ __('Fee and invoice records') }}</h2>
        @forelse($invoices as $invoice)
            <div class="course-item">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <strong>{{ $invoice->invoice_number }} · {{ $invoice->title }}</strong>
                    <span class="badge {{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ __(ucfirst($invoice->status)) }}</span>
                </div>
                <p class="muted">{{ __('Amount') }}: ৳{{ number_format((float) $invoice->amount, 2) }} · {{ __('Due date') }}: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</p>
                @foreach($invoice->payments as $payment)
                    <small class="muted">{{ __('Payment') }}: ৳{{ number_format((float) $payment->amount, 2) }} · {{ $payment->paid_at?->format('d M Y') }}</small><br>
                @endforeach
            </div>
        @empty
            <p class="muted">{{ __('No fee records are available yet.') }}</p>
        @endforelse
    </div>
@endif
@endsection
