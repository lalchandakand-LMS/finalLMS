@extends('layouts.app')
@section('title', $post->seo_title ?: $post->title)
@section('content')
<article class="panel" style="max-width:900px;margin:auto;">
@if($post->cover)<img src="{{ $post->cover->url }}" alt="{{ $post->cover->alt_text }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:14px;">@endif
<h1>{{ $post->title }}</h1><p class="muted">{{ $post->published_at->format('d M Y') }} · {{ $post->author->name }}</p>
@if($post->excerpt)<p><strong>{{ $post->excerpt }}</strong></p>@endif
<div style="white-space:pre-line;line-height:1.8;">{{ $post->body }}</div>
<p style="margin-top:24px;"><a href="{{ route('news.index') }}" class="btn secondary">{{ __('All news') }}</a></p>
</article>
@endsection