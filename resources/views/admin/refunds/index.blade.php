@extends('layouts.app')

@section('title', 'Refund management | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Finance control</span>
            <h1 style="margin:12px 0 0;">Refund management</h1>
            <p class="muted">Refunds are recorded against received payments. Admin approval is required before a refund is marked approved.</p>
        </div>
        <a class="btn secondary" href="{{ route(auth()->user()->isAccountant() ? 'accountant.invoices.index' : 'admin.invoices.index') }}">Back to invoices</a>
    </div>

    <section class="panel" style="margin-bottom:22px;">
        <h2>Request a refund</h2>
        <form method="POST" action="{{ route(auth()->user()->isAccountant() ? 'accountant.refunds.store' : 'admin.refunds.store') }}" class="grid grid-2">
            @csrf
            <div style="grid-column:1/-1;">
                <label for="invoice_payment_id">Received payment</label>
                <select id="invoice_payment_id" name="invoice_payment_id" required>
                    <option value="">Select a payment</option>
                    @foreach($payments as $payment)
                        <option value="{{ $payment->id }}" @selected(old('invoice_payment_id') == $payment->id)>
                            {{ $payment->invoice->invoice_number }} — {{ $payment->invoice->user->name }} — {{ ucfirst($payment->method) }} — ৳{{ number_format((float) $payment->amount, 2) }} ({{ $payment->paid_at->format('d M Y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="amount">Refund amount (BDT)</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label for="method">Refund method</label>
                <select id="method" name="method" required>
                    <option value="cash">Cash</option>
                    <option value="bkash">bKash</option>
                    <option value="nagad">Nagad</option>
                    <option value="bank">Bank</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label for="reference">Refund reference (optional)</label>
                <input id="reference" name="reference" maxlength="120" value="{{ old('reference') }}">
            </div>
            <div>
                <label for="reason">Reason</label>
                <input id="reason" name="reason" maxlength="500" value="{{ old('reason') }}" required>
            </div>
            <div><button class="btn primary" type="submit">Submit refund request</button></div>
        </form>
    </section>

    <section class="panel">
        <h2>Refund ledger</h2>
        <div class="course-list">
            @forelse($refunds as $refund)
                <article class="course-item">
                    <div class="meta-row" style="margin-top:0;">
                        <div>
                            <span class="badge {{ $refund->status === 'approved' ? 'success' : ($refund->status === 'pending' ? 'warning' : 'info') }}">{{ ucfirst($refund->status) }}</span>
                            <h3 style="margin:8px 0;">৳{{ number_format((float) $refund->amount, 2) }} — {{ $refund->invoice->user->name }}</h3>
                            <p class="muted" style="margin:4px 0;">{{ $refund->invoice->invoice_number }} · Payment #{{ $refund->invoice_payment_id }} · {{ ucfirst($refund->method) }} · {{ $refund->reason }}</p>
                            <p class="muted" style="margin:4px 0;">Requested by {{ $refund->requester->name }} · {{ $refund->created_at->format('d M Y H:i') }}
                                @if($refund->approver) · Processed by {{ $refund->approver->name }} @endif
                            </p>
                        </div>
                        @if($isAdmin && $refund->status === 'pending')
                            <div style="display:flex;gap:8px;align-items:center;">
                                <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}">
                                    @csrf
                                    <button class="btn primary" type="submit">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}">
                                    @csrf
                                    <button class="btn secondary" type="submit">Reject</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="muted">No refund requests yet.</p>
            @endforelse
        </div>
        <div style="margin-top:20px;">{{ $refunds->links() }}</div>
    </section>
@endsection
