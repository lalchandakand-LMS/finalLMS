@extends('layouts.app')

@section('title', $student->name . ' | ' . __('Guardian report'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Guardian report') }}</span><h1>{{ $student->name }}</h1><p class="muted">{{ $student->email }}</p></div>
    <a href="{{ route('guardian.dashboard') }}" class="btn secondary">{{ __('Back to children') }}</a>
</div>
<div class="grid grid-2">
    <section class="panel">
        <h2>{{ __('Courses & learning') }}</h2>
        @forelse($student->enrollments as $enrollment)
            <div class="card" style="margin-bottom:10px">
                <strong>{{ $enrollment->course?->title ?? __('Course unavailable') }}</strong>
                <div class="muted">{{ __('Status:') }} {{ ucfirst($enrollment->status) }}</div>
            </div>
        @empty
            <p class="muted">{{ __('No course enrollments yet.') }}</p>
        @endforelse
        <h3>{{ __('Certificates') }}</h3>
        @forelse($student->certificates as $certificate)
            <p>{{ $certificate->course?->title ?? __('Course') }} · {{ optional($certificate->issued_at)->format('d M Y') }}</p>
        @empty
            <p class="muted">{{ __('No certificates issued yet.') }}</p>
        @endforelse
    </section>
    <section class="panel">
        <h2>{{ __('Attendance') }}</h2>
        @forelse($student->attendances->sortByDesc('attendance_date')->take(20) as $attendance)
            <div class="aec-row"><span>{{ $attendance->attendance_date?->format('d M Y') }} · {{ $attendance->batch?->title }}</span><strong>{{ ucfirst($attendance->status) }}</strong></div>
        @empty
            <p class="muted">{{ __('No attendance records yet.') }}</p>
        @endforelse
    </section>
    <section class="panel" style="grid-column:1/-1">
        <h2>{{ __('Fee & invoice summary') }}</h2>
        <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Paid') }}</th></tr></thead><tbody>
        @forelse($student->invoices as $invoice)
            <tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td><td>৳{{ number_format((float)$invoice->amount,2) }}</td><td>{{ ucfirst($invoice->status) }}</td><td>৳{{ number_format((float)$invoice->payments->sum('amount'),2) }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">{{ __('No invoices found.') }}</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
