@extends('layouts.app')
@section('title','Digital Store')
@section('content')
<div class="container" style="max-width:1180px;margin:36px auto;padding:0 18px">
<h1>Courses & eBooks Store</h1><p>Choose a course or digital book from our catalog.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px">
@forelse($products as $product)
<article class="panel" style="padding:18px;border:1px solid var(--border);border-radius:14px;background:var(--panel)">
@if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="width:100%;height:150px;object-fit:cover;border-radius:10px">@endif
<h3>{{ $product->name }}</h3><p>{{ $product->short_description }}</p>
<div style="font-weight:800;font-size:1.15rem">৳{{ number_format((float)$product->currentPrice(auth()->id()),2) }}</div>
@if((float)$product->currentPrice(auth()->id()) < (float)$product->regular_price)<p><del class="muted">৳{{ number_format((float)$product->regular_price,2) }}</del> <span class="badge warning">Offer price</span></p>@endif
@if(auth()->check())<form method="POST" action="{{ route('store.cart.add',$product) }}">@csrf<button type="submit" class="btn btn-primary">Add to cart</button></form>@else<a class="btn btn-primary" href="{{ route('login') }}">Login to buy</a>@endif
</article>
@empty<p>No products are published yet.</p>@endforelse
</div>{{ $products->links() }}
</div>
@endsection