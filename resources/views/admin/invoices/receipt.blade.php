<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment receipt {{ $payment->id }} | {{ config('app.name') }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#172033;background:#f4f6fa;margin:0;padding:32px}
        .receipt{max-width:760px;margin:0 auto;background:#fff;padding:36px;border:1px solid #dce2ea;border-radius:12px}
        .top{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #172033;padding-bottom:20px}
        h1{margin:0 0 8px;font-size:26px}.muted{color:#64748b}.amount{font-size:28px;font-weight:700;margin:24px 0}
        table{width:100%;border-collapse:collapse;margin-top:20px}td{padding:12px 8px;border-bottom:1px solid #e5eaf0}td:first-child{color:#64748b;width:38%}
        .actions{max-width:760px;margin:16px auto;text-align:right}.btn{border:0;background:#172033;color:white;padding:11px 18px;border-radius:7px;cursor:pointer}
        @media print{body{background:#fff;padding:0}.receipt{border:0;border-radius:0}.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions"><button class="btn" onclick="window.print()">Print / Save PDF</button></div>
    <main class="receipt">
        <div class="top">
            <div><h1>{{ config('app.name', "Alom's English Center") }}</h1><div class="muted">Official payment receipt</div></div>
            <div style="text-align:right"><strong>RECEIPT</strong><div class="muted">#{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</div></div>
        </div>
        <div class="amount">৳{{ number_format((float) $payment->amount, 2) }}</div>
        <table>
            <tr><td>Student</td><td>{{ $payment->invoice->user->name }}</td></tr>
            <tr><td>Invoice</td><td>{{ $payment->invoice->invoice_number }}</td></tr>
            <tr><td>Fee / installment</td><td>{{ $payment->invoice->title }}</td></tr>
            <tr><td>Course</td><td>{{ $payment->invoice->course?->title ?? '—' }}</td></tr>
            <tr><td>Batch</td><td>{{ $payment->invoice->batch?->title ?? '—' }}</td></tr>
            <tr><td>Payment method</td><td>{{ strtoupper($payment->method) }}</td></tr>
            <tr><td>Transaction reference</td><td>{{ $payment->reference ?: '—' }}</td></tr>
            <tr><td>Payment date</td><td>{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</td></tr>
            <tr><td>Received by</td><td>{{ $payment->receiver?->name ?? '—' }}</td></tr>
            <tr><td>Note</td><td>{{ $payment->note ?: '—' }}</td></tr>
        </table>
        <p class="muted" style="margin-top:28px">This receipt confirms that the payment amount shown above has been recorded against the invoice.</p>
    </main>
</body>
</html>
