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
@php($line=(float)$item->product->currentPrice()*$item->quantity) @php($total+=$line)
<div class="panel" style="padding:16px;border:1px solid var(--border);border-radius:12px;display:flex;justify-content:space-between;gap:15px;align-items:center">
<div><strong>{{ $item->product->name }}</strong><div>৳{{ number_format((float)$item->product->currentPrice(),2) }} each</div></div>
<div style="display:flex;gap:8px;align-items:center">
<form method="POST" action="{{ route('store.cart.update',$item) }}">@csrf @method('PUT')<input type="number" name="quantity" min="1" max="10" value="{{ $item->quantity }}" style="width:70px"><button type="submit">Update</button></form>
<form method="POST" action="{{ route('store.cart.remove',$item) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form>
<strong>৳{{ number_format($line,2) }}</strong>
</div></div>
@endforeach
</div>
<h2>Total: ৳{{ number_format($total,2) }}</h2>
<form method="POST" action="{{ route('store.checkout') }}" class="panel" style="padding:18px;border:1px solid var(--border);border-radius:12px;max-width:540px">
@csrf<h3>Checkout — Manual Payment Submission</h3>
<label>Payment method</label><select name="payment_method" required style="display:block;width:100%;padding:10px;margin:6px 0 14px">
@foreach(collect(\App\Models\Setting::paymentMethods())->filter(fn($m)=>(bool)($m['enabled']??false)) as $key=>$method)
<option value="{{ $key }}">{{ $method['label'] ?? ucfirst($key) }}</option>
@endforeach
</select>
<label>Transaction ID / Reference</label><input name="payment_reference" required maxlength="120" style="display:block;width:100%;padding:10px;margin:6px 0 14px">
<p>Order access remains pending until an administrator verifies the payment.</p><button type="submit" class="btn btn-primary">Place order</button>
</form>
@endif
</div>
@endsection