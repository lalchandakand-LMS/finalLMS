@extends('layouts.app')
@section('title','Commerce Orders')
@section('content')
<div class="container" style="max-width:1200px;margin:30px auto;padding:0 18px"><h1>Commerce Orders</h1>
<p>Verify payment references before granting course or eBook access.</p>
@forelse($orders as $order)
<section class="panel" style="padding:18px;margin:14px 0;border:1px solid var(--border);border-radius:12px;background:var(--panel)">
<div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $order->order_number }}</strong><b>{{ strtoupper($order->status) }}</b></div>
<p>{{ $order->user->name }} · {{ $order->user->email }} · {{ $order->payment_method }} · Ref: {{ $order->payment_reference }}</p>
<ul>@foreach($order->items as $item)<li>{{ $item->product_name }} × {{ $item->quantity }} — ৳{{ number_format((float)$item->line_total,2) }}</li>@endforeach</ul>
<strong>Total: ৳{{ number_format((float)$order->total,2) }}</strong>
@if($order->status==='pending')
<div style="display:flex;gap:10px;margin-top:12px">
<form method="POST" action="{{ route('admin.orders.approve',$order) }}">@csrf<button type="submit" class="btn btn-primary">Verify & approve</button></form>
<form method="POST" action="{{ route('admin.orders.reject',$order) }}">@csrf<button type="submit" class="btn">Reject</button></form>
</div>
@endif
</section>
@empty<p>No orders found.</p>@endforelse{{ $orders->links() }}</div>
@endsection