@extends('layouts.app')
@section('title', 'eBook Management')
@section('content')
<div class="container" style="max-width:1180px;margin:30px auto;padding:0 18px">
    <h1>eBook Management</h1>
    <p class="muted">Upload PDF files to private storage. Customers can download only after an administrator verifies payment.</p>
    <div class="grid grid-2" style="align-items:start">
        <section class="panel">
            <h2>Create eBook</h2>
            <form method="POST" action="{{ route('admin.ebooks.store') }}" enctype="multipart/form-data">
                @csrf
                <label>Name<input name="name" maxlength="180" required></label>
                <label>Short description<textarea name="short_description" maxlength="500"></textarea></label>
                <label>Description<textarea name="description" rows="5"></textarea></label>
                <div class="grid grid-2">
                    <label>Price (BDT)<input type="number" name="regular_price" min="0" step="0.01" required></label>
                    <label>Sale price<input type="number" name="sale_price" min="0" step="0.01"></label>
                </div>
                <label>PDF file (required)<input type="file" name="download_file" accept="application/pdf,.pdf" required></label>
                <label>Cover image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                <label>Download limit (blank = unlimited)<input type="number" name="download_limit" min="1" max="1000"></label>
                <label>Access expiry (optional)<input type="datetime-local" name="download_expires_at"></label>
                <label>Categories</label>
                @foreach($categories as $category)
                    <label style="display:flex;gap:8px;align-items:center"><input style="width:auto" type="checkbox" name="category_ids[]" value="{{ $category->id }}"> {{ $category->name }}</label>
                @endforeach
                <label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
                <button class="btn primary" type="submit">Save eBook</button>
            </form>
        </section>
        <section class="panel">
            <h2>Create category</h2>
            <form method="POST" action="{{ route('admin.ebooks.categories.store') }}">
                @csrf
                <label>Category name<input name="name" maxlength="120" required></label>
                <label>Description<textarea name="description"></textarea></label>
                <label>Sort order<input type="number" name="sort_order" min="0" value="0"></label>
                <button class="btn secondary" type="submit">Add category</button>
            </form>
            <hr>
            @foreach($categories as $category)
                <div style="display:flex;justify-content:space-between;padding:8px 0">
                    <span>{{ $category->name }}</span>
                    <form method="POST" action="{{ route('admin.ebooks.categories.destroy',$category) }}">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete</button></form>
                </div>
            @endforeach
        </section>
    </div>

    <h2 style="margin-top:28px">Published and draft eBooks ({{ $products->total() }})</h2>
    @forelse($products as $product)
        <section class="panel" style="margin:12px 0">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div><h3>{{ $product->name }}</h3><p class="muted">{{ strtoupper($product->status) }} · ৳{{ number_format((float)$product->currentPrice(),2) }} · {{ $product->orderItems()->count() }} order item(s)</p></div>
                <form method="POST" action="{{ route('admin.ebooks.destroy',$product) }}">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete / Archive</button></form>
            </div>
            <details><summary>Edit eBook</summary>
                <form method="POST" action="{{ route('admin.ebooks.update',$product) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <label>Name<input name="name" value="{{ $product->name }}" required></label>
                    <label>Short description<textarea name="short_description">{{ $product->short_description }}</textarea></label>
                    <label>Description<textarea name="description" rows="3">{{ $product->description }}</textarea></label>
                    <div class="grid grid-2"><label>Price<input type="number" name="regular_price" min="0" step="0.01" value="{{ $product->regular_price }}" required></label><label>Sale price<input type="number" name="sale_price" min="0" step="0.01" value="{{ $product->sale_price }}"></label></div>
                    <label>Replace PDF (locked after first order)<input type="file" name="download_file" accept="application/pdf,.pdf" @disabled($product->orderItems()->exists())></label>
                    <label>Replace cover<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Download limit<input type="number" name="download_limit" min="1" max="1000" value="{{ $product->download_limit }}"></label>
                    <label>Expiry<input type="datetime-local" name="download_expires_at" value="{{ $product->download_expires_at?->format('Y-m-d\TH:i') }}"></label>
                    <label>Status<select name="status"><option value="draft" @selected($product->status==='draft')>Draft</option><option value="published" @selected($product->status==='published')>Published</option><option value="archived" @selected($product->status==='archived')>Archived</option></select></label>
                    <label>Categories</label>
                    @foreach($categories as $category)<label style="display:flex;gap:8px;align-items:center"><input style="width:auto" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked($product->categories->contains('id',$category->id))> {{ $category->name }}</label>@endforeach
                    <button class="btn primary" type="submit">Update eBook</button>
                </form>
            </details>
        </section>
    @empty
        <p>No eBooks created yet.</p>
    @endforelse
    {{ $products->links() }}
</div>
@endsection
