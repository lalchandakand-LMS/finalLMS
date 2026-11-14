@extends('layouts.app')
@section('title', __('eBook Sales Report'))
@section('content')
<div class="container" style="max-width:1200px;margin:32px auto;padding:0 18px">
    <div class="page-header">
        <div><span class="badge info">{{ __('Reports') }}</span><h1>{{ __('eBook Sales Report') }}</h1><p class="muted">{{ __('Paid order totals less manually recorded refunds.') }}</p></div>
        <a class="btn secondary" href="{{ route('admin.ebooks.orders.index') }}">{{ __('Back to orders') }}</a>
    </div>
    <form method="GET" class="panel" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:18px">
        <label>{{ __('From date') }}<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>{{ __('To date') }}<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button class="btn primary" type="submit">{{ __('Filter report') }}</button>
        <a class="btn secondary" href="{{ route('admin.ebooks.sales') }}">{{ __('Clear') }}</a>
    </form>
    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
        <div class="panel"><small class="muted">{{ __('Paid orders') }}</small><h2>{{ number_format($orderCount) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Gross sales') }}</small><h2>৳{{ number_format($grossSales,2) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Refunds') }} ({{ $refundCount }})</small><h2>৳{{ number_format($refundTotal,2) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Net sales') }}</small><h2>৳{{ number_format($netSales,2) }}</h2></div>
    </div>
    <section class="panel" style="margin-bottom:20px">
        <h2>{{ __('Sales by eBook') }}</h2>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr><th>{{ __('eBook') }}</th><th>{{ __('Units sold') }}</th><th>{{ __('Gross sales') }}</th><th>{{ __('Refunded') }}</th><th>{{ __('Net sales') }}</th></tr></thead>
                <tbody>
                @forelse($productSales as $sale)
                    <tr style="border-top:1px solid var(--border)">
                        <td style="padding:10px">{{ $sale->product_name }}</td>
                        <td>{{ number_format((int)$sale->units_sold) }}</td>
                        <td>৳{{ number_format((float)$sale->gross_sales,2) }}</td>
                        <td>৳{{ number_format((float)$sale->refunded_sales,2) }}</td>
                        <td><strong>৳{{ number_format(max(0,(float)$sale->gross_sales-(float)$sale->refunded_sales),2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="padding:16px">{{ __('No paid eBook sales found for this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <h2>{{ __('Processed refunds') }}</h2>
        @forelse($refunds as $refund)
            <div class="course-item" style="padding:12px 0;border-bottom:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <strong>{{ $refund->order?->order_number }}</strong>
                    <strong>৳{{ number_format((float)$refund->amount,2) }}</strong>
                </div>
                <p class="muted">{{ $refund->order?->user?->name }} · {{ $refund->refund_reference }} · {{ $refund->processed_at?->format('d M Y, h:i A') }}</p>
                <p>{{ $refund->reason }}</p>
            </div>
        @empty
            <p class="muted">{{ __('No refunds have been recorded for this period.') }}</p>
        @endforelse
        {{ $refunds->withQueryString()->links() }}
    </section>
</div>
@endsection
