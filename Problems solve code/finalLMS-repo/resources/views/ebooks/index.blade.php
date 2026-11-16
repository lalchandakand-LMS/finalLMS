@extends('layouts.app')
@section('title', 'eBook Store')
@section('content')
<div class="container" style="max-width:1180px;margin:32px auto;padding:0 18px">
    <h1>Digital eBook Store</h1>
    <p>Purchase English learning books and access your private PDF downloads after payment verification.</p>
    <div class="grid grid-3">
        @forelse($products as $product)
            <article class="panel">
                @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" style="width:100%;height:170px;object-fit:cover;border-radius:10px">@endif
                <h3>{{ $product->name }}</h3>
                <p>{{ $product->short_description }}</p>
                <strong>৳{{ number_format((float)$product->currentPrice(),2) }}</strong>
                <p><a class="btn primary" href="{{ route('ebooks.show',$product->slug) }}">View eBook</a></p>
            </article>
        @empty
            <p>No eBooks are published yet.</p>
        @endforelse
    </div>
    {{ $products->links() }}
</div>
@endsection
