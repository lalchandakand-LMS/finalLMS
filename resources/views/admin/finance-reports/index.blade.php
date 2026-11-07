@extends('layouts.app')

@section('title', 'Finance reports | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $accountant ? 'Branch finance report' : 'Organization finance report' }}</span>
            <h1 style="margin:12px 0 0;">Finance reports</h1>
            <p class="muted">{{ $branch ? $branch->name : 'All branches' }} · Collection is filtered by payment date; outstanding is the current balance.</p>
        </div>
        <button class="btn secondary" type="button" onclick="window.print()">Print report</button>
    </div>

    <section class="panel no-print" style="margin-bottom:20px;">
        <form method="GET" action="{{ route($accountant ? 'accountant.finance-reports.index' : 'admin.finance-reports.index') }}" class="grid grid-2">
            <div><label for="date_from">From date</label><input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}"></div>
            <div><label for="date_to">To date</label><input id="date_to" type="date" name="date_to" value="{{ $dateTo }}"></div>
            <div><button class="btn primary" type="submit">Apply date filter</button> <a class="btn secondary" href="{{ route($accountant ? 'accountant.finance-reports.index' : 'admin.finance-reports.index') }}">Clear</a></div>
        </form>
    </section>

    <div class="dash-summary">
        <div class="summary-box"><span class="muted">Collected in selected period</span><strong>৳{{ number_format($collected, 2) }}</strong><small>{{ $paymentCount }} transactions</small></div>
        <div class="summary-box"><span class="muted">Invoices issued in period</span><strong>{{ number_format($issuedInvoices) }}</strong><small>৳{{ number_format($issuedAmount, 2) }} billed</small></div>
        <div class="summary-box"><span class="muted">Current outstanding</span><strong>৳{{ number_format($outstanding, 2) }}</strong><small>All unpaid / partial invoices</small></div>
        <div class="summary-box"><span class="muted">Average collection</span><strong>৳{{ number_format($paymentCount ? $collected / $paymentCount : 0, 2) }}</strong><small>Per transaction</small></div>
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <h2>Payment method summary</h2>
        @if($methodTotals->isEmpty())
            <p class="muted">No payments found for this period.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;">
                    <thead><tr><th style="padding:10px;">Method</th><th style="padding:10px;">Transactions</th><th style="padding:10px;">Collected</th></tr></thead>
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
        <h2>Payment ledger</h2>
        @if($payments->isEmpty())
            <p class="muted">No payment records found for this period.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;min-width:760px;">
                    <thead><tr><th style="padding:10px;">Date</th><th style="padding:10px;">Student</th><th style="padding:10px;">Invoice</th><th style="padding:10px;">Method / Reference</th><th style="padding:10px;">Received by</th><th style="padding:10px;text-align:right;">Amount</th><th class="no-print" style="padding:10px;">Receipt</th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->paid_at->format('d M Y, h:i A') }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->invoice->user->name }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->invoice->invoice_number }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ ucfirst($payment->method) }}<br><small class="muted">{{ $payment->reference ?: '—' }}</small></td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $payment->receiver->name }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);text-align:right;">৳{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="no-print" style="padding:10px;border-top:1px solid var(--border);"><a href="{{ route('invoice-payments.receipt', $payment) }}" target="_blank" rel="noopener">Print</a></td>
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
