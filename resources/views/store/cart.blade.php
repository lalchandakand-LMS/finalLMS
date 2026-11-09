@extends('layouts.app')
@section('title','Shopping Cart')
@section('content')
<div class="container" style="max-width:1000px;margin:36px auto;padding:0 18px">
<h1>Your Cart</h1>
@if($cart->items->isEmpty())<p>Your cart is empty. <a href="{{ route('store.index') }}">Browse the store</a>.</p>
@else
@php($total=0)
<div style="display:grid;gap:12px">
@foreach($cart->items as $item)
@php($available=$item->product && !$item->product->trashed() && $item->product->status==='published') @php($line=$available ? (float)$item->product->currentPrice(auth()->id())*$item->quantity : 0) @php($total+=$line)
<div class="panel" style="padding:16px;border:1px solid var(--border);border-radius:12px;display:flex;justify-content:space-between;gap:15px;align-items:center">
<div><strong>{{ $item->product?->name ?? 'Unavailable product' }}</strong><div>@if($available)৳{{ number_format((float)$item->product->currentPrice(auth()->id()),2) }} each @else <span class="badge warning">Unavailable — remove this item before checkout</span> @endif</div></div>
<div style="display:flex;gap:8px;align-items:center">
<form method="POST" action="{{ route('store.cart.update',$item) }}">@csrf @method('PUT')<input type="number" name="quantity" min="1" max="10" value="{{ $item->quantity }}" style="width:70px"><button type="submit">Update</button></form>
<form method="POST" action="{{ route('store.cart.remove',$item) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form>
<strong>৳{{ number_format($line,2) }}</strong>
</div></div>
@endforeach
</div>
<h2>Total: ৳{{ number_format($total,2) }}</h2>
@if($cart->items->contains(fn ($item) => !$item->product || $item->product->trashed() || $item->product->status!=='published'))<p class="flash error">Remove unavailable products before placing your order.</p>@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('store.checkout') }}" class="panel" style="padding:18px;border:1px solid var(--border);border-radius:12px;max-width:540px">
@csrf<h3>Secure Checkout</h3>
<label>Coupon code (optional)</label><input name="coupon_code" maxlength="60" placeholder="Enter coupon code" style="display:block;width:100%;padding:10px;margin:6px 0 14px">
<label>Payment method</label><select name="payment_method" required style="display:block;width:100%;padding:10px;margin:6px 0 14px">
@foreach(collect(\App\Models\Setting::paymentMethods())->filter(fn($m)=>(bool)($m['enabled']??false)) as $key=>$method)
<option value="{{ $key }}">{{ $method['label'] ?? ucfirst($key) }}</option>
@endforeach
</select>
<label>Transaction ID / Reference (manual payments only)</label><input name="payment_reference" maxlength="120" placeholder="Not required for bKash checkout" style="display:block;width:100%;padding:10px;margin:6px 0 14px">
<label>Sender mobile / account (optional)</label><input name="payment_sender" maxlength="60" placeholder="01XXXXXXXXX" style="display:block;width:100%;padding:10px;margin:6px 0 14px">
<label>Payment screenshot / receipt (optional, JPG/PNG/PDF)</label><input type="file" name="payment_proof" accept="image/jpeg,image/png,application/pdf" style="display:block;width:100%;padding:10px;margin:6px 0 14px">
<p>Manual payments are verified by the academy. For bKash, you will be redirected to the secure gateway after placing the order.</p><button type="submit" class="btn btn-primary">Place order</button>
</form>
@endif
</div>
@endsection