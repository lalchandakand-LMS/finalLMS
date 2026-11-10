@extends('layouts.app')
@section('title', $post->seo_title ?: $post->title)
@section('seo_title', $post->seo_title ?: $post->title)
@section('seo_description', $post->seo_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 160)))
@section('content')
<article class="panel" style="max-width:900px;margin:auto;">
@if($post->cover)<img src="{{ $post->cover->url }}" alt="{{ $post->cover->alt_text }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:14px;">@endif
<h1>{{ $post->title }}</h1>
@if($post->categories->isNotEmpty())<div class="meta-row">@foreach($post->categories as $category)<span class="badge info">{{ $category->name }}</span>@endforeach</div>@endif
<p class="muted">{{ $post->published_at->format('d M Y') }} · {{ $post->author->name }}</p>
@if($post->excerpt)<p><strong>{{ $post->excerpt }}</strong></p>@endif
<div style="white-space:pre-line;line-height:1.8;">{{ $post->body }}</div>
@if($post->video_url)<p><a href="{{ $post->video_url }}" target="_blank" rel="noopener noreferrer">{{ __('Watch lesson video') }}</a></p>@endif
@if(is_array($post->tags) && count($post->tags))<p class="muted">{{ __('Tags') }}: {{ implode(', ', $post->tags) }}</p>@endif
<p style="margin-top:24px;"><a href="{{ route('news.index') }}" class="btn secondary">{{ __('All news') }}</a></p>
</article>
@endsection
