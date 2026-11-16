@extends('layouts.app')
@section('title', $product->name.' | eBook Store')
@section('content')
<div class="container" style="max-width:900px;margin:32px auto;padding:0 18px">
    <section class="panel">
        @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="max-width:320px;width:100%;border-radius:12px">@endif
        <h1>{{ $product->name }}</h1>
        <p>{{ $product->short_description }}</p>
        <div>{!! nl2br(e($product->description)) !!}</div>
        @if($product->previewPages->isNotEmpty())
            <section aria-labelledby="ebook-preview-heading" style="margin:22px 0">
                <h2 id="ebook-preview-heading">{{ __('Preview sample pages') }}</h2>
                <p class="muted">{{ __('View sample pages before purchasing. Full PDF access remains protected by your order entitlement.') }}</p>
                <div class="grid grid-3">
                    @foreach($product->previewPages as $previewPage)
                        <a href="{{ route('ebooks.preview', [$product->slug, $previewPage]) }}" target="_blank" rel="noopener noreferrer" class="course-item" style="display:block;text-decoration:none">
                            <img src="{{ route('ebooks.preview', [$product->slug, $previewPage]) }}" alt="{{ $previewPage->alt_text ?: __('Sample page :number', ['number' => $previewPage->page_number]) }}" loading="lazy" style="width:100%;max-height:280px;object-fit:contain;border-radius:8px">
                            <span class="muted">{{ __('Sample page :number', ['number' => $previewPage->page_number]) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
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
