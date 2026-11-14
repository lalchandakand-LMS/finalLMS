@extends('layouts.app')
@section('title', 'eBook Orders')
@section('content')
<div class="container" style="max-width:1150px;margin:32px auto;padding:0 18px">
    <h1>eBook Orders & Payment Verification</h1>
    <p>Approve only after independently checking the transaction with the payment provider or academy account.</p>
    @forelse($orders as $order)
        <section class="panel" style="margin:12px 0">
            <div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $order->order_number }}</strong><strong>{{ strtoupper($order->status) }}</strong></div>
            <p>{{ $order->user->name }} · {{ $order->user->email }}</p>
            <p>৳{{ number_format((float)$order->total,2) }} · Reference: {{ $order->payment_reference }} · Sender: {{ $order->payment_sender ?: '—' }}</p>
            @if($order->payment_proof_path)<p><a href="{{ route('admin.ebooks.orders.proof',$order) }}">View private payment proof</a></p>@endif
            @foreach($order->items as $item)<p>{{ $item->product_name }} × {{ $item->quantity }}</p>@endforeach
            @if($order->status === 'pending')
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <form method="POST" action="{{ route('admin.ebooks.orders.approve',$order) }}">@csrf<button class="btn primary" type="submit">Verify & grant download</button></form>
                    <form method="POST" action="{{ route('admin.ebooks.orders.reject',$order) }}">@csrf<button class="btn secondary" type="submit">Reject order</button></form>
                </div>
            @endif
        </section>
    @empty
        <p>No eBook orders yet.</p>
    @endforelse
    {{ $orders->links() }}
</div>
@endsection
