@extends('layouts.app')

@section('title', 'Invoices & finance | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ auth()->user()->isAccountant() ? "Accountant finance" : "Admin finance" }}</span>
            <h1 style="margin: 12px 0 0;">Invoices & installments</h1>
            <p><a class="btn secondary" href="{{ route(auth()->user()->isAccountant() ? 'accountant.finance-reports.index' : 'admin.finance-reports.index') }}">Open finance reports</a></p>
            <p class="muted">Create a separate invoice for each fee or installment and record received payments.</p>
        </div>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.payments.index') }}" class="btn secondary">Online payment review</a>
        @endif
    </div>

    <section class="panel" style="margin-bottom: 22px;">
        <h2>Create student invoice</h2>
        <form method="POST" action="{{ route(auth()->user()->isAccountant() ? 'accountant.invoices.store' : 'admin.invoices.store') }}" class="grid grid-2">
            @csrf
            <div>
                <label for="user_id">Student</label>
                <select id="user_id" name="user_id" required>
                    <option value="">Select student</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="title">Invoice title / installment</label>
                <input id="title" name="title" value="{{ old('title') }}" placeholder="e.g. Spoken English — Installment 1" required maxlength="160">
            </div>
            <div>
                <label for="course_id">Course (optional)</label>
                <select id="course_id" name="course_id">
                    <option value="">Not linked to a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="batch_id">Batch ID (optional)</label>
                <input id="batch_id" name="batch_id" type="number" min="1" value="{{ old('batch_id') }}" placeholder="Enter batch ID">
            </div>
            <div>
                <label for="amount">Invoice subtotal before discount (BDT)</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label for="discount_type">Discount type (optional)</label>
                <select id="discount_type" name="discount_type">
                    <option value="">No discount</option>
                    <option value="fixed" @selected(old('discount_type') === 'fixed')>Fixed amount (BDT)</option>
                    <option value="percentage" @selected(old('discount_type') === 'percentage')>Percentage (%)</option>
                </select>
            </div>
            <div>
                <label for="discount_value">Discount value</label>
                <input id="discount_value" name="discount_value" type="number" min="0" step="0.01" value="{{ old('discount_value', 0) }}">
            </div>
            <div>
                <label for="due_date">Due date (optional)</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="description">Description / installment details</label>
                <textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description') }}</textarea>
            </div>
            <div><button class="btn primary" type="submit">Create invoice</button></div>
        </form>
    </section>

    <div class="course-list">
        @forelse($invoices as $invoice)
            @php($paid = (float) $invoice->payments->sum('amount'))
            @php($due = max(0, (float) $invoice->amount - $paid))
            <article class="course-item">
                <div class="meta-row" style="margin-top:0;">
                    <div>
                        <span class="badge {{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'info') }}">{{ ucfirst($invoice->status) }}</span>
                        <h2 style="margin: 10px 0 6px;">{{ $invoice->title }}</h2>
                        <div class="muted">{{ $invoice->invoice_number }} · {{ $invoice->user->name }} ({{ $invoice->user->email }})</div>
                        <div class="muted">Subtotal ৳{{ number_format((float) ($invoice->subtotal ?? $invoice->amount), 2) }}
                            @if((float) ($invoice->discount_value ?? 0) > 0)
                                · Discount {{ $invoice->discount_type === 'percentage' ? number_format((float) $invoice->discount_value, 2).'%' : '৳'.number_format((float) $invoice->discount_value, 2) }}
                            @endif
                            · Net total ৳{{ number_format((float) $invoice->amount, 2) }} · Paid ৳{{ number_format($paid, 2) }} · Due ৳{{ number_format($due, 2) }}</div>
                        @if($invoice->due_date)<div class="muted">Due date: {{ $invoice->due_date->format('d M Y') }}</div>@endif
                        @if($invoice->description)<p>{{ $invoice->description }}</p>@endif
                    </div>
                </div>
                @if($due > 0 && $invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route(auth()->user()->isAccountant() ? 'accountant.invoices.payments.store' : 'admin.invoices.payments.store', $invoice) }}" class="grid grid-2" style="margin-top:16px;">
                        @csrf
                        <div><label>Received amount (BDT)</label><input type="number" name="amount" min="0.01" max="{{ number_format($due, 2, '.', '') }}" step="0.01" required></div>
                        <div><label>Payment method</label><select name="method" required><option value="cash">Cash</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="bank">Bank</option><option value="online">Online gateway</option><option value="other">Other</option></select></div>
                        <div><label>Transaction / receipt reference</label><input name="reference" maxlength="120"></div>
                        <div><label>Received date</label><input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                        <div style="grid-column:1/-1;"><label>Note</label><input name="note" maxlength="1000"></div>
                        <div><button class="btn primary" type="submit">Record payment</button></div>
                    </form>
                @endif
                @if($invoice->payments->isNotEmpty())
                    <details style="margin-top:14px;">
                        <summary>Payment history ({{ $invoice->payments->count() }})</summary>
                        @foreach($invoice->payments as $receipt)
                            <p class="muted">৳{{ number_format((float) $receipt->amount, 2) }} · {{ ucfirst($receipt->method) }} · {{ $receipt->paid_at->format('d M Y H:i') }} · {{ $receipt->reference ?: 'No reference' }} · <a href="{{ route('invoice-payments.receipt', $receipt) }}" target="_blank" rel="noopener">Print receipt</a></p>
                        @endforeach
                    </details>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted">No invoices have been created yet.</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $invoices->links() }}</div>
@endsection
