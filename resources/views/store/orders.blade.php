@extends('layouts.app')
@section('title','My Orders')
@section('content')
<div class="container" style="max-width:1100px;margin:36px auto;padding:0 18px"><h1>My Orders</h1>
@forelse($orders as $order)
<section class="panel" style="padding:18px;margin:14px 0;border:1px solid var(--border);border-radius:12px">
<div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $order->order_number }}</strong><span>{{ strtoupper($order->status) }}</span></div>
<p>{{ $order->created_at->format('d M Y, h:i A') }} · ৳{{ number_format((float)$order->total,2) }} · Ref: {{ $order->payment_reference }}</p>
@if($order->status === 'pending' && $order->payment_method === 'bkash')
<form method="POST" action="{{ route('store.orders.pay.bkash', $order) }}" style="margin:12px 0">
@csrf
<button type="submit" class="btn btn-primary">Pay ৳{{ number_format((float)$order->total,2) }} with bKash</button>
</form>
@elseif($order->status === 'pending' && $order->payment_method !== 'bkash')
<p class="muted">Payment is awaiting academy verification.</p>
@endif
@foreach($order->paymentTransactions as $transaction)
@if($transaction->provider === 'bkash')
<p class="muted">bKash payment status: {{ strtoupper($transaction->status) }}@if($transaction->gateway_transaction_id) · TrxID: {{ $transaction->gateway_transaction_id }}@endif</p>
@endif
@endforeach
<ul>@foreach($order->items as $item)<li>{{ $item->product_name }} × {{ $item->quantity }}</li>@endforeach</ul>
@if($order->status==='completed')
@foreach($order->items as $item)
@if($item->product_type==='ebook')
@php($permission=\App\Models\DownloadPermission::where('order_item_id',$item->id)->where('user_id',auth()->id())->first())
@if($permission)<a href="{{ route('store.download',$permission) }}">Download {{ $item->product_name }}</a>@endif
@endif
@endforeach
@endif
</section>
@empty<p>You have not placed any orders yet.</p>@endforelse{{ $orders->links() }}</div>
@endsection