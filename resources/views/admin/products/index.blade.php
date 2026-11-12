@extends('layouts.app')
@section('title', 'Product Management | '.config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge info">Commerce · WooCommerce-inspired</span><h1 style="margin:10px 0 0;">Product Management</h1><p class="muted">Manage sellable courses and protected PDF/eBook products.</p></div></div>

<div class="grid grid-2" style="align-items:start;">
    <section class="panel">
        <h2>Create product</h2>
        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
            @csrf
            <label>Product type<select name="type" required><option value="course">Course product</option><option value="ebook">Downloadable eBook / PDF</option></select></label>
            <label>Product name<input name="name" maxlength="180" required></label>
            <label>Linked course<select name="course_id"><option value="">Select course (required for course product)</option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
            <label>SKU<input name="sku" maxlength="80"></label>
            <label>Short description<textarea name="short_description" maxlength="500" rows="2"></textarea></label>
            <label>Full description<textarea name="description" rows="5"></textarea></label>
            <div class="grid grid-2">
                <label>Regular price (BDT)<input type="number" name="regular_price" min="0" step="0.01" value="0" required></label>
                <label>Sale price (optional)<input type="number" name="sale_price" min="0" step="0.01"></label>
            </div>
            <label>Product thumbnail<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <label>PDF file (required for eBook)<input type="file" name="download_file" accept="application/pdf"></label>
            <label>Download limit (blank = unlimited)<input type="number" name="download_limit" min="1" max="1000"></label>
            <label>Product categories</label>
            <div class="grid grid-2">@forelse($categories as $category)<label style="display:flex;gap:8px;align-items:center;font-weight:500;"><input style="width:auto;" type="checkbox" name="category_ids[]" value="{{ $category->id }}"> {{ $category->name }}</label>@empty<p class="muted">Create a category below.</p>@endforelse</div>
            <label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label>
            <label style="display:flex;gap:8px;align-items:center;"><input style="width:auto;" type="checkbox" name="featured" value="1"> Featured product</label>
            <button class="btn primary" type="submit">Save product</button>
        </form>
    </section>

    <section class="panel">
        <h2>Product categories</h2>
        <form method="POST" action="{{ route('admin.product-categories.store') }}">
            @csrf
            <label>Category name<input name="name" maxlength="120" required></label>
            <label>Description<textarea name="description" rows="2"></textarea></label>
            <label>Display order<input type="number" name="sort_order" min="0" value="0"></label>
            <button class="btn secondary" type="submit">Create category</button>
        </form>
        <hr style="margin:20px 0;border-color:var(--border);">
        @foreach($categories as $category)
            <div style="padding:12px 0;border-bottom:1px solid var(--border);">
                <form method="POST" action="{{ route('admin.product-categories.update',$category) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-2"><label>Name<input name="name" value="{{ $category->name }}" required></label><label>Sort order<input type="number" name="sort_order" value="{{ $category->sort_order }}"></label></div>
                    <label>Description<textarea name="description" rows="1">{{ $category->description }}</textarea></label>
                    <label>Status<select name="is_active"><option value="1" @selected($category->is_active)>Active</option><option value="0" @selected(!$category->is_active)>Hidden</option></select></label>
                    <button class="btn secondary" type="submit">Update category</button>
                </form>
                <form method="POST" action="{{ route('admin.product-categories.destroy',$category) }}" onsubmit="return confirm('Delete category? Products will be preserved.')" style="margin-top:8px;">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete category</button></form>
            </div>
        @endforeach
    </section>
</div>

<div class="page-header" style="margin-top:28px;"><h2>Products</h2><span class="badge info">{{ $products->total() }} total</span></div>
@forelse($products as $product)
<div class="panel" style="margin-bottom:14px;">
    <div class="meta-row" style="margin-top:0;"><div><h3 style="margin:0;">{{ $product->name }}</h3><p class="muted" style="margin:5px 0;">{{ strtoupper($product->type) }} · SKU: {{ $product->sku ?: '—' }} · {{ $product->status }}</p></div><strong>৳{{ number_format((float)$product->currentPrice(),2) }}</strong></div>
    <p class="muted">{{ $product->short_description }}</p>
    <p class="muted">Categories: {{ $product->categories->pluck('name')->join(', ') ?: 'None' }} @if($product->course) · Course: {{ $product->course->title }} @endif</p>
    <details><summary style="cursor:pointer;font-weight:700;">Edit product</summary>
        <form method="POST" action="{{ route('admin.products.update',$product) }}" enctype="multipart/form-data" style="margin-top:14px;">
            @csrf @method('PUT')
            <label>Type<select name="type"><option value="course" @selected($product->type==='course')>Course</option><option value="ebook" @selected($product->type==='ebook')>eBook/PDF</option></select></label>
            <label>Name<input name="name" value="{{ $product->name }}" required></label>
            <label>Linked course<select name="course_id"><option value="">None</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($product->course_id===$course->id)>{{ $course->title }}</option>@endforeach</select></label>
            <label>SKU<input name="sku" value="{{ $product->sku }}"></label>
            <label>Short description<textarea name="short_description">{{ $product->short_description }}</textarea></label>
            <label>Full description<textarea name="description" rows="3">{{ $product->description }}</textarea></label>
            <div class="grid grid-2"><label>Regular price<input type="number" name="regular_price" min="0" step="0.01" value="{{ $product->regular_price }}" required></label><label>Sale price<input type="number" name="sale_price" min="0" step="0.01" value="{{ $product->sale_price }}"></label></div>
            <label>Replace thumbnail<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <label>Replace PDF (optional)<input type="file" name="download_file" accept="application/pdf"></label>
            <label>Download limit<input type="number" name="download_limit" min="1" max="1000" value="{{ $product->download_limit }}"></label>
            <label>Categories</label><div class="grid grid-2">@foreach($categories as $category)<label style="display:flex;gap:8px;align-items:center;font-weight:500;"><input style="width:auto;" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked($product->categories->contains('id',$category->id))> {{ $category->name }}</label>@endforeach</div>
            <label>Status<select name="status">@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected($product->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label style="display:flex;gap:8px;align-items:center;"><input style="width:auto;" type="checkbox" name="featured" value="1" @checked($product->featured)> Featured</label>
            <button class="btn primary" type="submit">Update product</button>
        </form>
    </details>
    <form method="POST" action="{{ route('admin.products.destroy',$product) }}" onsubmit="return confirm('Delete or archive this product?')" style="margin-top:10px;">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete / Archive</button></form>
</div>
@empty
<div class="panel"><p class="muted">No products created yet.</p></div>
@endforelse
<div>{{ $products->links() }}</div>
@endsection
