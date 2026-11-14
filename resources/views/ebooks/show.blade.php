@extends('layouts.app')
@section('title', $product->name.' | eBook Store')
@section('content')
<div class="container" style="max-width:900px;margin:32px auto;padding:0 18px">
    <section class="panel">
        @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="max-width:320px;width:100%;border-radius:12px">@endif
        <h1>{{ $product->name }}</h1>
        <p>{{ $product->short_description }}</p>
        <div>{!! nl2br(e($product->description)) !!}</div>
        <h2>৳{{ number_format((float)$product->currentPrice(),2) }} <small>BDT</small></h2>
        @auth
            <form method="POST" action="{{ route('ebooks.checkout',$product->slug) }}" enctype="multipart/form-data">
                @csrf
                <p>Payment method: Manual (academy verification required)</p>
                <label>Transaction reference<input name="payment_reference" maxlength="120" required></label>
                <label>Sender account (optional)<input name="payment_sender" maxlength="60"></label>
                <label>Payment screenshot / receipt (optional)<input type="file" name="payment_proof" accept="image/jpeg,image/png,application/pdf"></label>
                <button class="btn primary" type="submit">Submit order</button>
            </form>
        @else
            <a class="btn primary" href="{{ route('login') }}">Login to purchase</a>
        @endauth
    </section>
</div>
@endsection
