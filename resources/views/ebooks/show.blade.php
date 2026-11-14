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
            @if((float)$product->currentPrice() > 0)
                @if($bkashAvailable)
                    <form method="POST" action="{{ route('ebooks.checkout.bkash', $product->slug) }}" style="margin:16px 0">
                        @csrf
                        <button class="btn primary" type="submit">{{ __('Pay securely with bKash') }}</button>
                    </form>
                @endif
                <div class="panel" style="margin-top:14px">
                    <p>{{ __('Or pay manually using the academy payment instructions and submit the transaction reference for verification.') }}</p>
                    <form method="POST" action="{{ route('ebooks.checkout',$product->slug) }}" enctype="multipart/form-data">
                        @csrf
                        <label>{{ __('Manual payment transaction reference') }}<input name="payment_reference" maxlength="120" required></label>
                        <label>{{ __('Sender account (optional)') }}<input name="payment_sender" maxlength="60"></label>
                        <label>{{ __('Payment screenshot / receipt (optional)') }}<input type="file" name="payment_proof" accept="image/jpeg,image/png,application/pdf"></label>
                        <button class="btn secondary" type="submit">{{ __('Submit manual payment order') }}</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('ebooks.checkout',$product->slug) }}">
                    @csrf
                    <p>{{ __('This eBook is free. Your download access will be activated immediately.') }}</p>
                    <button class="btn primary" type="submit">{{ __('Add free eBook to library') }}</button>
                </form>
            @endif
        @else
            <a class="btn primary" href="{{ route('login') }}">Login to purchase</a>
        @endauth
    </section>
</div>
@endsection
