@extends('layouts.app')
@section('title', 'Digital Store | '.config('app.name'))
@section('content')
<div class="container" style="max-width:1180px;margin:36px auto;padding:0 18px">
    <div class="page-header"><div><span class="badge info">Digital library</span><h1>Courses & eBooks Store</h1><p class="muted">Buy courses and protected digital books from Alom’s English Center.</p></div></div>
    <form method="GET" action="{{ route('store.index') }}" class="panel" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <input name="q" value="{{ request('q') }}" placeholder="Search courses and eBooks" style="flex:1;min-width:180px;">
        <select name="category" style="min-width:180px;"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category')===$category->slug)>{{ $category->name }}</option>@endforeach</select>
        <button class="btn secondary" type="submit">Search</button>
        <a class="btn secondary" href="{{ route('store.index') }}">Clear</a>
    </form>
    <div class="grid grid-3">
    @forelse($products as $product)
        <article class="panel" style="padding:18px;">
            @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="width:100%;height:170px;object-fit:cover;border-radius:10px">@endif
            <div class="meta-row"><span class="badge">{{ $product->type==='ebook' ? 'eBook / PDF' : 'Course' }}</span>@if($product->featured)<span class="badge warning">Featured</span>@endif</div>
            <h3>{{ $product->name }}</h3><p class="muted">{{ $product->short_description }}</p>
            <div style="font-weight:800;font-size:1.15rem">৳{{ number_format((float)$product->currentPrice(auth()->id()),2) }}</div>
            @if((float)$product->currentPrice(auth()->id()) < (float)$product->regular_price)<p><del class="muted">৳{{ number_format((float)$product->regular_price,2) }}</del> <span class="badge warning">Offer price</span></p>@endif
            @if($product->preview_path)<a class="btn secondary" href="{{ route('store.preview',$product) }}" target="_blank" rel="noopener">Read sample PDF</a>@endif
            <a class="btn secondary" href="{{ route('store.products.show',$product) }}">View details</a>
            @if(auth()->check() && auth()->user()->isStudent())
                <form method="POST" action="{{ route('store.cart.add',$product) }}">@csrf<button type="submit" class="btn primary">Add to cart</button></form>
            @elseif(!auth()->check())
                <a class="btn primary" href="{{ route('login') }}">Login to buy</a>
            @else
                <p class="muted">Student account required to purchase.</p>
            @endif
        </article>
    @empty
        <div class="panel"><p class="muted">No products match your search yet.</p></div>
    @endforelse
    </div>
    <div style="margin-top:18px;">{{ $products->links() }}</div>
</div>
@endsection