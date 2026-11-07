@extends('layouts.app')

@section('title', __('Finance reports') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $accountant ? __('Branch finance report') : __('Organization finance report') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Finance reports') }}</h1>
            <p class="muted">{{ $branch ? $branch->name : __('All branches') }} · {{ __('Collection is filtered by payment date; outstanding is the current balance.') }}</p>
        </div>
        <button class="btn secondary" type="button" onclick="window.print()">{{ __('Print report') }}</button>
    </div>

    <section class="panel no-print" style="margin-bottom:20px;">
        <form method="GET" action="{{ route($accountant ? 'accountant.finance-reports.index' : 'admin.finance-reports.index') }}" class="grid grid-2">
            <div><label for="date_from">{{ __('From date') }}</label><input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}"></div>
            <div><label for="date_to">{{ __('To date') }}</label><input id="date_to" type="date" name="date_to" value="{{ $dateTo }}"></div>
            <div><button class="btn primary" type="submit">{{ __('Apply date filter') }}</button> <a class="btn secondary" href="{{ route($accountant ? 'accountant.finance-reports.index' : 'admin.finance-reports.index') }}">{{ __('Clear') }}</a></div>
        </form>
    </section>

    <div class="dash-summary">
        <div class="summary-box"><span class="muted">{{ __('Collected in selected period') }}</span><strong>৳{{ number_format($collected, 2) }}</strong><small>{{ $paymentCount }} {{ __('transactions') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Expenses in selected period') }}</span><strong>৳{{ number_format($expenses, 2) }}</strong><small>{{ $expenseCount }} {{ __('records') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Approved refunds') }}</span><strong>৳{{ number_format($refundTotal, 2) }}</strong><small>{{ $refundCount }} {{ __('refunds in selected period') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Net cash movement') }}</span><strong>৳{{ number_format($netBalance, 2) }}</strong><small>{{ __('Collections minus refunds and expenses') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Invoices issued in period') }}</span><strong>{{ number_format($issuedInvoices) }}</strong><small>৳{{ number_format($issuedAmount, 2) }} {{ __('billed') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Current outstanding') }}</span><strong>৳{{ number_format($outstanding, 2) }}</strong><small>{{ __('All unpaid / partial invoices') }}</small></div>
        <div class="summary-box"><span class="muted">{{ __('Average collection') }}</span><strong>৳{{ number_format($paymentCount ? $collected / $paymentCount : 0, 2) }}</strong><small>{{ __('Per transaction') }}</small></div>
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <h2>{{ __('Expense category summary') }}</h2>
        @if($expenseCategoryTotals->isEmpty())
            <p class="muted">{{ __('No expenses found for this period.') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;">
                    <thead><tr><th style="padding:10px;">{{ __('Category') }}</th><th style="padding:10px;">{{ __('Records') }}</th><th style="padding:10px;">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                    @foreach($expenseCategoryTotals as $expenseCategory)
                        <tr><td style="padding:10px;border-top:1px solid var(--border);">{{ $expenseCategory->category }}</td><td style="padding:10px;border-top:1px solid var(--border);">{{ $expenseCategory->records }}</td><td style="padding:10px;border-top:1px solid var(--border);">৳{{ number_format((float) $expenseCategory->total, 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel" style="margin-bottom:20px;">
        <h2>{{ __('Payment method summary') }}</h2>
        @if($methodTotals->isEmpty())
            <p class="muted">{{ __('No payments found for this period.') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;">
                    <thead><tr><th style="padding:10px;">{{ __('Method') }}</th><th style="padding:10px;">{{ __('Transactions') }}</th><th style="padding:10px;">{{ __('Collected') }}</th></tr></thead>
                    <tbody>
                    @foreach($methodTotals as $method)
                        <tr><td style="padding:10px;border-top:1px solid var(--border);">{{ ucfirst($method->method) }}</td><td style="padding:10px;border-top:1px solid var(--border);">{{ $method->transactions }}</td><td style="padding:10px;border-top:1px solid var(--border);">৳{{ number_format((float) $method->total, 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <h2>{{ __('Payment ledger') }}</h2>
        @if($payments->isEmpty())
            <p class="muted">{{ __('No payment records found for this period.') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;min-width:760px;">
                    <thead><tr><th style="padding:10px;">{{ __('Date') }}</th><th style="padding:10px;">{{ __('Student') }}</th><th style="padding:10px;">{{ __('Invoice') }}</th><th style="padding:10px;">{{ __('Method / Reference') }}</th><th style="padding:10px;">{{ __('Received by') }}</th><th style="padding:10px;text-align:right;">{{ __('Amount') }}</th><th class="no-print" style="padding:10px;">{{ __('Receipt') }}</th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->paid_at->format('d M Y, h:i A') }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->invoice->user->name }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->invoice->invoice_number }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ ucfirst($payment->method) }}<br><small class="muted">{{ $payment->reference ?: '—' }}</small></td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->receiver?->name ?? __('Online gateway') }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);text-align:right;">৳{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="no-print" style="padding:10px;border-top:1px solid var(--border);"><a href="{{ route('invoice-payments.receipt', $payment) }}" target="_blank" rel="noopener">{{ __('Print') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:18px;">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection

<style>
@media print {
    .topbar, footer, .no-print, button, form { display:none !important; }
    body { background:#fff !important; }
    .container { max-width:none !important; padding:0 !important; }
    .panel, .summary-box { box-shadow:none !important; break-inside:avoid; }
    a { color:#000 !important; }
}
</style>
