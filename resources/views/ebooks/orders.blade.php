@extends('layouts.app')
@section('title', 'My eBook Orders')
@section('content')
<div class="container" style="max-width:1000px;margin:32px auto;padding:0 18px">
    <h1>My eBook Library & Orders</h1>
    @forelse($orders as $order)
        <section class="panel" style="margin:12px 0">
            <div style="display:flex;justify-content:space-between;gap:12px"><strong>{{ $order->order_number }}</strong><span>{{ strtoupper($order->status) }}</span></div>
            <p>৳{{ number_format((float)$order->total,2) }} · Ref: {{ $order->payment_reference }}</p>
            @foreach($order->items as $item)
                <div style="padding:8px 0">
                    <strong>{{ $item->product_name }}</strong>
                    @php($permission = $permissions->get($item->id))
                    @if($permission && $order->status === 'completed')
                        <p><a class="btn primary" href="{{ route('ebooks.download',$permission) }}">Download PDF</a>
                        <small>Downloads used: {{ $permission->download_count }} @if($permission->downloads_remaining !== null) · Remaining: {{ $permission->downloads_remaining }} @else · Unlimited downloads @endif</small></p>
                    @elseif($order->status === 'pending')
                        <p class="muted">Payment awaiting academy verification.</p>
                    @else
                        <p class="muted">No active download permission.</p>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p>Your eBook orders will appear here.</p>
    @endforelse
    {{ $orders->links() }}
</div>
@endsection
