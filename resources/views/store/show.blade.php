@extends('layouts.app')
@section('title', ($product->seo_title ?: $product->name).' | '.config('app.name'))
@section('content')
<div class="container" style="max-width:1000px;margin:36px auto;padding:0 18px">
    <div class="grid grid-2" style="align-items:start;">
        <section class="panel">
            @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:12px">@endif
            <span class="badge info">{{ $product->type==='ebook' ? 'Digital eBook / PDF' : 'Course product' }}</span>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->short_description }}</p>
            <div class="muted">{!! nl2br(e($product->description)) !!}</div>
            @if($product->tags)<p class="muted">Tags: {{ implode(', ', $product->tags) }}</p>@endif
        </section>
        <aside class="panel">
            <h2>৳{{ number_format((float)$product->currentPrice(auth()->id()),2) }}</h2>
            @if((float)$product->currentPrice(auth()->id()) < (float)$product->regular_price)<p><del class="muted">৳{{ number_format((float)$product->regular_price,2) }}</del></p>@endif
            <p class="muted">Category: {{ $product->categories->pluck('name')->join(', ') ?: 'General' }}</p>
            @if($product->type==='ebook')
                <p>Secure PDF access is granted after payment verification. Download limits and expiry are controlled by the academy.</p>
                @if($product->preview_path)<a class="btn secondary" href="{{ route('store.preview',$product) }}" target="_blank" rel="noopener">Read sample PDF</a>@endif
            @elseif($product->course)<p>Linked course: {{ $product->course->title }}</p>@endif
            @if(auth()->check() && auth()->user()->isStudent())
                <form method="POST" action="{{ route('store.cart.add',$product) }}">@csrf<button class="btn primary" type="submit">Add to cart</button></form>
            @elseif(!auth()->check())<a class="btn primary" href="{{ route('login') }}">Login to buy</a>
            @else<p class="muted">Student account required to purchase.</p>@endif
        </aside>
    </div>
</div>
@endsection