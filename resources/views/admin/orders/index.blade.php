@extends('layouts.app')
@section('title','Commerce Orders')
@section('content')
<div class="container" style="max-width:1200px;margin:30px auto;padding:0 18px"><h1>Commerce Orders</h1>
<p>Verify payment references before granting course or eBook access. Refund recording does not send money automatically; return funds through the original payment channel.</p>
<div class="grid grid-4" style="margin:18px 0;">
    <div class="panel"><small>Total orders</small><h2>{{ $summary['total'] }}</h2></div>
    <div class="panel"><small>Pending</small><h2>{{ $summary['pending'] }}</h2></div>
    <div class="panel"><small>Completed revenue</small><h2>৳{{ number_format($summary['revenue'],2) }}</h2></div>
    <div class="panel"><small>Refunded</small><h2>{{ $summary['refunded'] }}</h2></div>
    <div class="panel"><small>Authorized reads / downloads</small><h2>{{ $summary['downloads'] }}</h2></div>
</div>
@forelse($orders as $order)
<section class="panel" style="padding:18px;margin:14px 0;border:1px solid var(--border);border-radius:12px;background:var(--panel)">
<div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $order->order_number }}</strong><b>{{ strtoupper($order->status) }}</b></div>
<p>{{ $order->user->name }} · {{ $order->user->email }} · {{ $order->payment_method }} · Ref: {{ $order->payment_reference }}</p>
@if($order->payment_sender)<p>Sender account: {{ $order->payment_sender }}</p>@endif
@foreach($order->paymentTransactions as $transaction)
<div style="padding:10px;margin:10px 0;border:1px solid var(--border);border-radius:8px">
<strong>{{ strtoupper($transaction->provider) }} gateway transaction</strong>
<div>Status: {{ strtoupper($transaction->status) }} · Amount: ৳{{ number_format((float)$transaction->amount,2) }} {{ $transaction->currency }}</div>
<div>Invoice: {{ $transaction->invoice }}</div>
@if($transaction->gateway_payment_id)<div>Gateway Payment ID: {{ $transaction->gateway_payment_id }}</div>@endif
@if($transaction->gateway_transaction_id)<div>Gateway TrxID: {{ $transaction->gateway_transaction_id }}</div>@endif
@if($transaction->verified_at)<div>Verified at: {{ $transaction->verified_at->format('d M Y, h:i A') }}</div>@endif
</div>
@endforeach
@if($order->payment_proof_path)<p><a href="{{ route('admin.orders.proof',$order) }}">View payment proof (private)</a></p>@endif
<ul>@foreach($order->items as $item)<li>{{ $item->product_name }} × {{ $item->quantity }} — ৳{{ number_format((float)$item->line_total,2) }}</li>@endforeach</ul>
<strong>Total: ৳{{ number_format((float)$order->total,2) }}</strong>
@foreach($order->items as $item)
    @foreach($item->downloadPermissions as $permission)
        <p class="muted">Download access #{{ $permission->id }} · {{ $permission->download_count }} reads/downloads · {{ $permission->downloads_remaining ?? 'Unlimited' }} remaining · {{ $permission->revoked_at ? 'Revoked' : 'Active' }}</p>
        @foreach($permission->logs->take(5) as $log)
            <small class="muted">{{ $log->downloaded_at?->format('d M Y H:i') }} · {{ strtoupper($log->action) }} · {{ $log->ip_address ?: 'IP unavailable' }}</small><br>
        @endforeach
        @if(!$permission->revoked_at)
            <form method="POST" action="{{ route('admin.download-permissions.revoke',$permission) }}" style="margin:8px 0;">@csrf<label>Reason<input name="reason" maxlength="1000" required placeholder="Reason for revoking access"></label><button class="btn secondary" type="submit">Revoke download access</button></form>
        @endif
    @endforeach
@endforeach
@if($order->status==='completed' && $order->items->isNotEmpty() && $order->items->every(fn($item)=>$item->product_type==='ebook'))
<form method="POST" action="{{ route('admin.orders.record-refund',$order) }}" style="margin-top:10px;">@csrf<label>Refund note<input name="refund_note" maxlength="2000" required placeholder="Refund reference / reason"></label><button class="btn secondary" type="submit">Record refund & revoke access</button></form>
@endif
@if($order->status==='pending')
<div style="display:flex;gap:10px;margin-top:12px">
<form method="POST" action="{{ route('admin.orders.approve',$order) }}">@csrf<button type="submit" class="btn btn-primary">Verify & approve</button></form>
<form method="POST" action="{{ route('admin.orders.reject',$order) }}">@csrf<button type="submit" class="btn">Reject</button></form>
</div>
@endif
</section>
@empty<p>No orders found.</p>@endforelse{{ $orders->links() }}</div>
@endsection