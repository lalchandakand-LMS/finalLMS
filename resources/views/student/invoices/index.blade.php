@extends('layouts.app')

@section('title', 'My invoices | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Student portal</span>
            <h1 style="margin:12px 0 0;">My fees & invoices</h1>
            <p class="muted">View your fee invoices, installment balances and recorded receipts.</p>
        </div>
    </div>

    <div class="course-list">
        @forelse($invoices as $invoice)
            @php($paid = (float) $invoice->payments->sum('amount'))
            @php($due = max(0, (float) $invoice->amount - $paid))
            <article class="course-item">
                <div class="meta-row" style="margin-top:0;">
                    <div>
                        <span class="badge {{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'info') }}">{{ ucfirst($invoice->status) }}</span>
                        <h2 style="margin:10px 0 6px;">{{ $invoice->title }}</h2>
                        <div class="muted">{{ $invoice->invoice_number }}@if($invoice->course) · {{ $invoice->course->title }}@endif</div>
                        <p>Total: <strong>৳{{ number_format((float) $invoice->amount, 2) }}</strong> · Paid: <strong>৳{{ number_format($paid, 2) }}</strong> · Outstanding: <strong>৳{{ number_format($due, 2) }}</strong></p>
                        @if($invoice->due_date)<div class="muted">Due date: {{ $invoice->due_date->format('d M Y') }}</div>@endif
                        @if($invoice->description)<p>{{ $invoice->description }}</p>@endif
                    </div>
                </div>
                @if($invoice->payments->isNotEmpty())
                    <details style="margin-top:12px;">
                        <summary>Receipts ({{ $invoice->payments->count() }})</summary>
                        @foreach($invoice->payments as $receipt)
                            <p class="muted">৳{{ number_format((float) $receipt->amount, 2) }} · {{ ucfirst($receipt->method) }} · {{ $receipt->paid_at->format('d M Y H:i') }} · Receipt ref: {{ $receipt->reference ?: 'N/A' }}</p>
                        @endforeach
                    </details>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted">You do not have any invoices yet.</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $invoices->links() }}</div>
@endsection
