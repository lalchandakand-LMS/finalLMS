@extends('layouts.app')

@section('title', __('Invoices & finance') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ auth()->user()->isAccountant() ? __('Accountant finance') : __('Admin finance') }}</span>
            <h1 style="margin: 12px 0 0;">{{ __('Invoices & installments') }}</h1>
            <p><a class="btn secondary" href="{{ route(auth()->user()->isAccountant() ? 'admin.finance.reports.index' : 'admin.finance-reports.index') }}">{{ __('Open finance reports') }}</a></p>
            <p class="muted">{{ __('Create a separate invoice for each fee or installment and record received payments.') }}</p>
        </div>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.payments.index') }}" class="btn secondary">{{ __('Online payment review') }}</a>
        @endif
    </div>

    <section class="panel" style="margin-bottom: 22px;">
        <h2>{{ __('Create student invoice') }}</h2>
        <form method="POST" action="{{ route(auth()->user()->isAccountant() ? 'admin.finance.invoices.store' : 'admin.invoices.store') }}" class="grid grid-2">
            @csrf
            <div>
                <label for="user_id">{{ __('Student') }}</label>
                <select id="user_id" name="user_id" required>
                    <option value="">{{ __('Select student') }}</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="title">{{ __('Invoice title / installment') }}</label>
                <input id="title" name="title" value="{{ old('title') }}" placeholder="{{ __('e.g. Spoken English — Installment 1') }}" required maxlength="160">
            </div>
            <div>
                <label for="course_id">{{ __('Course (optional)') }}</label>
                <select id="course_id" name="course_id">
                    <option value="">{{ __('Not linked to a course') }}</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="batch_id">{{ __('Batch ID (optional)') }}</label>
                <input id="batch_id" name="batch_id" type="number" min="1" value="{{ old('batch_id') }}" placeholder="{{ __('Enter batch ID') }}">
            </div>
            <div>
                <label for="amount">{{ __('Invoice subtotal before discount (BDT)') }}</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label for="discount_type">{{ __('Discount type (optional)') }}</label>
                <select id="discount_type" name="discount_type">
                    <option value="">{{ __('No discount') }}</option>
                    <option value="fixed" @selected(old('discount_type') === 'fixed')>{{ __('Fixed amount (BDT)') }}</option>
                    <option value="percentage" @selected(old('discount_type') === 'percentage')>{{ __('Percentage (%)') }}</option>
                </select>
            </div>
            <div>
                <label for="discount_value">{{ __('Discount value') }}</label>
                <input id="discount_value" name="discount_value" type="number" min="0" step="0.01" value="{{ old('discount_value', 0) }}">
            </div>
            <div>
                <label for="due_date">{{ __('Due date (optional)') }}</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="description">{{ __('Description / installment details') }}</label>
                <textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description') }}</textarea>
            </div>
            <div><button class="btn primary" type="submit">{{ __('Create invoice') }}</button></div>
        </form>
    </section>

    <div class="course-list">
        @forelse($invoices as $invoice)
            @php($refunded = (float) $invoice->refunds->where('status', 'approved')->sum('amount'))
            @php($paid = max(0, (float) $invoice->payments->sum('amount') - $refunded))
            @php($due = max(0, (float) $invoice->amount - $paid))
            <article class="course-item">
                <div class="meta-row" style="margin-top:0;">
                    <div>
                        <span class="badge {{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'info') }}">{{ __(ucfirst($invoice->status)) }}</span>
                        <h2 style="margin: 10px 0 6px;">{{ $invoice->title }}</h2>
                        <div class="muted">{{ $invoice->invoice_number }} · {{ $invoice->user->name }} ({{ $invoice->user->email }})</div>
                        <div class="muted">{{ __('Subtotal') }} ৳{{ number_format((float) ($invoice->subtotal ?? $invoice->amount), 2) }}
                            @if((float) ($invoice->discount_value ?? 0) > 0)
                                · {{ __('Discount') }} {{ $invoice->discount_type === 'percentage' ? number_format((float) $invoice->discount_value, 2).'%' : '৳'.number_format((float) $invoice->discount_value, 2) }}
                            @endif
                            · {{ __('Net total') }} ৳{{ number_format((float) $invoice->amount, 2) }} · {{ __('Net paid') }} ৳{{ number_format($paid, 2) }} · {{ __('Refunded') }} ৳{{ number_format($refunded, 2) }} · {{ __('Due') }} ৳{{ number_format($due, 2) }}</div>
                        @if($invoice->due_date)<div class="muted">{{ __('Due date:') }} {{ $invoice->due_date->format('d M Y') }}</div>@endif
                        @if($invoice->description)<p>{{ $invoice->description }}</p>@endif
                    </div>
                </div>
                @if($due > 0 && $invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route(auth()->user()->isAccountant() ? 'admin.finance.invoices.payments.store' : 'admin.invoices.payments.store', $invoice) }}" class="grid grid-2" style="margin-top:16px;">
                        @csrf
                        <div><label>{{ __('Received amount (BDT)') }}</label><input type="number" name="amount" min="0.01" max="{{ number_format($due, 2, '.', '') }}" step="0.01" required></div>
                        <div><label>{{ __('Payment method') }}</label><select name="method" required><option value="cash">{{ __('Cash') }}</option><option value="bkash">{{ __('bKash') }}</option><option value="nagad">{{ __('Nagad') }}</option><option value="bank">{{ __('Bank') }}</option><option value="online">{{ __('Online gateway') }}</option><option value="other">{{ __('Other') }}</option></select></div>
                        <div><label>{{ __('Transaction / receipt reference') }}</label><input name="reference" maxlength="120"></div>
                        <div><label>{{ __('Received date') }}</label><input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                        <div style="grid-column:1/-1;"><label>{{ __('Note') }}</label><input name="note" maxlength="1000"></div>
                        <div><button class="btn primary" type="submit">{{ __('Record payment') }}</button></div>
                    </form>
                @endif
                @if($invoice->payments->isNotEmpty())
                    <details style="margin-top:14px;">
                        <summary>{{ __('Payment history') }} ({{ $invoice->payments->count() }})</summary>
                        @foreach($invoice->payments as $receipt)
                            <p class="muted">৳{{ number_format((float) $receipt->amount, 2) }} · {{ __(ucfirst($receipt->method)) }} · {{ $receipt->paid_at->format('d M Y H:i') }} · {{ $receipt->reference ?: __('No reference') }} · <a href="{{ route(auth()->user()->isAccountant() ? 'admin.finance.invoice-payments.receipt' : 'invoice-payments.receipt', $receipt) }}" target="_blank" rel="noopener">{{ __('Print receipt') }}</a></p>
                        @endforeach
                    </details>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No invoices have been created yet.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $invoices->links() }}</div>
@endsection
