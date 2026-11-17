@extends('layouts.app')
@section('title', $post->exists ? __('Edit content post') : __('Create content post'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ $post->exists ? __('Edit content post') : __('Create content post') }}</h1></div><a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Back to studio') }}</a></div>
<div class="panel" style="max-width:980px;">
<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.course-content-posts.update', $post) : route('admin.course-content-posts.store') }}">
@csrf @if($post->exists)@method('PUT')@endif
<label>{{ __('Title') }}<input name="title" maxlength="180" value="{{ old('title', $post->title) }}" required></label>
<label>{{ __('Slug') }}<input name="slug" maxlength="200" value="{{ old('slug', $post->slug) }}" placeholder="{{ __('Generated from title if empty') }}"></label>
<label>{{ __('Short description') }}<textarea name="short_description" maxlength="500" rows="2">{{ old('short_description', $post->short_description) }}</textarea></label>
<label>{{ __('Long description / lesson content') }}<textarea name="long_description" maxlength="100000" rows="10" required>{{ old('long_description', $post->long_description) }}</textarea></label>
<label>{{ __('YouTube or Vimeo video URL') }}<input type="url" name="video_url" maxlength="500" value="{{ old('video_url', $post->video_url) }}" placeholder="https://youtu.be/..."></label>

<section style="padding:16px;margin:16px 0;border:1px solid var(--border);border-radius:12px;">
<h2 style="margin-top:0">{{ __('Thumbnail & lesson files') }}</h2>
@if($post->thumbnail_path)
<img src="{{ asset('storage/'.$post->thumbnail_path) }}" alt="{{ $post->title }}" style="max-width:220px;max-height:140px;object-fit:cover;border-radius:8px;display:block;margin-bottom:10px;">
<label><input type="checkbox" name="remove_thumbnail" value="1" style="width:auto;"> {{ __('Remove current uploaded thumbnail') }}</label>
@elseif($post->thumbnail)
<img src="{{ $post->thumbnail->url }}" alt="{{ $post->thumbnail->alt_text ?: $post->title }}" style="max-width:220px;max-height:140px;object-fit:cover;border-radius:8px;display:block;margin-bottom:10px;">
@endif
<label>{{ __('Upload thumbnail to Media Library (JPG, PNG, WebP or GIF; max 5 MB)') }}<input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
<label>{{ __('Or choose from Media Library') }}<select name="thumbnail_media_id"><option value="">{{ __('Use uploaded thumbnail / no library image') }}</option>@foreach($media as $item)<option value="{{ $item->id }}" @selected((string)old('thumbnail_media_id', $post->thumbnail_media_id) === (string)$item->id)>{{ $item->original_name }}</option>@endforeach</select></label>
@if($post->content_file_path)
<p class="muted">{{ __('Current lesson file') }}: {{ $post->content_file_name }} ({{ number_format(($post->content_file_size ?? 0) / 1024, 1) }} KB)</p>
<label><input type="checkbox" name="remove_content_file" value="1" style="width:auto;"> {{ __('Remove current lesson file') }}</label>
@endif
<label>{{ __('Upload lesson file (PDF, MP4, MP3, image or ZIP; max 100 MB)') }}<input type="file" name="content_file" accept=".pdf,.mp4,.mp3,.jpg,.jpeg,.png,.webp,.zip,application/pdf,video/mp4,audio/mpeg,image/jpeg,image/png,image/webp,application/zip"></label>
<p class="muted">{{ __('Lesson files are stored privately. Paid PDF downloads are delivered through the existing verified eBook checkout and download-permission system.') }}</p>
</section>

<section style="padding:16px;margin:16px 0;border:1px solid var(--border);border-radius:12px;">
<h2 style="margin-top:0">{{ __('Access & pricing') }}</h2>
<label>{{ __('Access type') }}<select name="access_type" id="access-type" required>
<option value="course_enrollment" @selected(old('access_type', $post->access_type ?: 'course_enrollment') === 'course_enrollment')>{{ __('Enrolled course students only') }}</option>
<option value="free" @selected(old('access_type', $post->access_type) === 'free')>{{ __('Free content') }}</option>
<option value="paid" @selected(old('access_type', $post->access_type) === 'paid')>{{ __('Sell PDF (paid)') }}</option>
</select></label>
<div id="paid-pricing" class="grid grid-2">
<label>{{ __('Regular price (BDT)') }}<input type="number" name="regular_price" min="0.01" max="999999999" step="0.01" value="{{ old('regular_price', $post->regular_price) }}"></label>
<label>{{ __('Sale price (optional)') }}<input type="number" name="sale_price" min="0" max="999999999" step="0.01" value="{{ old('sale_price', $post->sale_price) }}"></label>
</div>
<label>{{ __('Academic Level') }}<select name="academic_level_id"><option value="">{{ __('All / Not assigned') }}</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string)old('academic_level_id', $post->academic_level_id) === (string)$level->id)>{{ $level->name }}</option>@endforeach</select></label>
@if($post->product_id)
<p class="muted">{{ __('Linked eBook product') }}: #{{ $post->product_id }} · {{ __('Orders and download permissions are managed in eBook Orders.') }}</p>
@endif
</section>

@if($post->product)
<section style="padding:16px;margin:16px 0;border:1px solid var(--border);border-radius:12px;">
<h2 style="margin-top:0">{{ __('PDF Preview Pages') }} ({{ $post->product->previewPages->count() }}/10)</h2>
<p class="muted">{{ __('Upload up to ten sample page images. Full PDF downloads remain protected by payment approval and download permissions.') }}</p>
@if($post->product->previewPages->isNotEmpty())
<div class="grid grid-3" style="margin:12px 0;">
@foreach($post->product->previewPages as $previewPage)
<article class="course-item">
<a href="{{ route('ebooks.preview', [$post->product->slug, $previewPage]) }}" target="_blank" rel="noopener noreferrer">
<img src="{{ route('ebooks.preview', [$post->product->slug, $previewPage]) }}" alt="{{ $previewPage->alt_text ?: __('Sample page :number', ['number' => $previewPage->page_number]) }}" loading="lazy" style="width:100%;max-height:180px;object-fit:contain;border-radius:8px;">
</a>
<p class="muted">{{ __('Page :number', ['number' => $previewPage->page_number]) }}</p>
<form method="POST" action="{{ route('admin.ebooks.preview-pages.destroy', [$post->product, $previewPage]) }}" onsubmit="return confirm('{{ __('Remove this sample page?') }}')">
@csrf @method('DELETE')
<button class="btn secondary" type="submit">{{ __('Remove preview') }}</button>
</form>
</article>
@endforeach
</div>
@endif
@if($post->product->previewPages->count() < 10)
<form method="POST" action="{{ route('admin.ebooks.preview-pages.store', $post->product) }}" enctype="multipart/form-data">
@csrf
<label>{{ __('Sample page image') }}<input type="file" name="page_image" accept="image/jpeg,image/png,image/webp" required></label>
<label>{{ __('Alternative text (optional)') }}<input type="text" name="alt_text" maxlength="180"></label>
<button class="btn primary" type="submit">{{ __('Upload preview page') }}</button>
</form>
@endif
</section>
@endif

<label>{{ __('Categories (select one or more)') }}</label>
<div class="grid grid-3" style="margin-bottom:16px;">@foreach($categories as $category)<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $post->exists ? $post->categories->pluck('id')->all() : []))) style="width:auto;">{{ $category->name }}</label>@endforeach</div>
<label>{{ __('Tags (comma separated)') }}<input name="tags" maxlength="1000" value="{{ old('tags', implode(', ', $post->tags ?? [])) }}" placeholder="grammar, speaking, beginner"></label>
<label>{{ __('SEO title') }}<input name="seo_title" maxlength="180" value="{{ old('seo_title', $post->seo_title) }}"></label>
<label>{{ __('SEO description') }}<textarea name="seo_description" maxlength="320">{{ old('seo_description', $post->seo_description) }}</textarea></label>
<div class="grid grid-2"><label>{{ __('Status') }}<select name="status" required>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $post->status ?: 'draft') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label><label>{{ __('Publish date/time (optional)') }}<input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></label></div>
<div style="display:flex;gap:10px;margin-top:18px;"><button class="btn primary" type="submit">{{ __('Save content post') }}</button><a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Cancel') }}</a></div>
</form></div>
<script>
(() => {
    const access = document.getElementById('access-type');
    const pricing = document.getElementById('paid-pricing');
    const refresh = () => { pricing.hidden = access.value !== 'paid'; };
    access.addEventListener('change', refresh);
    refresh();
})();
</script>
@endsection
