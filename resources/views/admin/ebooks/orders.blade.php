@extends('layouts.app')
@section('title', __('eBook Orders'))
@section('content')
<div class="container" style="max-width:1150px;margin:32px auto;padding:0 18px">
    <div class="page-header">
        <div><h1>{{ __('eBook Orders & Payment Verification') }}</h1><p class="muted">{{ __('Verify payment independently before granting access. Record external refunds before revoking the order.') }}</p></div>
        <a class="btn secondary" href="{{ route('admin.ebooks.sales') }}">{{ __('Sales report') }}</a>
    </div>
    @forelse($orders as $order)
        <section class="panel" style="margin:12px 0">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><strong>{{ $order->order_number }}</strong><strong>{{ __(ucfirst($order->status)) }}</strong></div>
            <p>{{ $order->user->name }} · {{ $order->user->email }}</p>
            <p>৳{{ number_format((float)$order->total,2) }} · {{ __('Reference') }}: {{ $order->payment_reference }} · {{ __('Sender') }}: {{ $order->payment_sender ?: '—' }}</p>
            @foreach($order->payments as $payment)
                <p class="muted">{{ strtoupper($payment->method) }} · {{ __('Gateway status') }}: {{ __(ucfirst($payment->status)) }}
                    @if($payment->gateway_transaction_id) · {{ __('Gateway transaction') }}: {{ $payment->gateway_transaction_id }} @endif
                    @if($payment->admin_note) · {{ $payment->admin_note }} @endif
                </p>
            @endforeach
            @if($order->payment_proof_path)<p><a href="{{ route('admin.ebooks.orders.proof',$order) }}">{{ __('View private payment proof') }}</a></p>@endif
            @foreach($order->items as $item)
                <div style="padding:10px 0;border-top:1px solid var(--border)">
                    <p><strong>{{ $item->product_name }} × {{ $item->quantity }}</strong></p>
                    @if($item->downloadPermission)
                        @if($item->downloadPermission->revoked_at)
                            <span class="badge warning">{{ __('Access revoked') }}</span>
                            <p class="muted">{{ $item->downloadPermission->revocation_reason }}</p>
                        @else
                            <span class="badge success">{{ __('Download access active') }}</span>
                            <p class="muted">{{ __('Downloads remaining') }}: {{ $item->downloadPermission->downloads_remaining ?? __('Unlimited') }} · {{ __('Downloads used') }}: {{ $item->downloadPermission->download_count }}</p>
                            <form method="POST" action="{{ route('admin.ebooks.permissions.revoke', $item->downloadPermission) }}" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                                @csrf
                                <input type="text" name="reason" maxlength="2000" required placeholder="{{ __('Reason for access revocation') }}" style="max-width:420px">
                                <button class="btn secondary" type="submit">{{ __('Revoke access') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach
            @if($order->refunds->isNotEmpty())
                @foreach($order->refunds as $refund)
                    <div class="flash warning" style="margin-top:10px">
                        <strong>{{ __('Refund recorded') }} · ৳{{ number_format((float)$refund->amount,2) }}</strong>
                        <p>{{ __('Refund reference') }}: {{ $refund->refund_reference }} · {{ __('Processed by') }}: {{ $refund->processor?->name ?: '—' }}</p>
                        <p>{{ $refund->reason }}</p>
                        <small>{{ $refund->processed_at?->format('d M Y, h:i A') }}</small>
                    </div>
                @endforeach
            @endif
            @if($order->status === 'pending')
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
                    <form method="POST" action="{{ route('admin.ebooks.orders.approve',$order) }}">@csrf<button class="btn primary" type="submit">{{ __('Verify & grant download') }}</button></form>
                    <form method="POST" action="{{ route('admin.ebooks.orders.reject',$order) }}">@csrf<button class="btn secondary" type="submit">{{ __('Reject order') }}</button></form>
                </div>
            @elseif($order->status === 'completed' && (float) $order->total > 0 && $order->refunds->isEmpty())
                <form method="POST" action="{{ route('admin.ebooks.orders.refund',$order) }}" class="panel" style="margin-top:12px;background:var(--surface-muted)">
                    @csrf
                    <h3>{{ __('Record full refund') }}</h3>
                    <p class="muted">{{ __('Only submit after the money has been returned outside the LMS. This records the refund and revokes all eBook access for this order.') }}</p>
                    <label>{{ __('External refund transaction/reference') }}<input type="text" name="refund_reference" maxlength="120" required></label>
                    <label>{{ __('Refund reason') }}<textarea name="reason" rows="2" maxlength="2000" required></textarea></label>
                    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="confirm_refund" value="1" required style="width:auto">{{ __('I confirm the refund has already been sent to the customer.') }}</label>
                    <button class="btn secondary" type="submit">{{ __('Record refund and revoke access') }}</button>
                </form>
            @endif
        </section>
    @empty
        <p>{{ __('No eBook orders yet.') }}</p>
    @endforelse
    {{ $orders->links() }}
</div>
@endsection
