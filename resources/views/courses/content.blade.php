@extends('layouts.app')
@section('title', $post->seo_title ?: $post->title)
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Course lesson') }}</span><h1>{{ $post->title }}</h1><p class="muted">{{ $course->title }} · {{ $item->duration_minutes ? $item->duration_minutes.' '.__('minutes') : '' }}</p></div><a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Back to course') }}</a></div>
<div class="panel" style="max-width:960px;margin:0 auto;">
@if($post->video_embed_url)
<div style="position:relative;aspect-ratio:16/9;margin-bottom:22px;"><iframe src="{{ $post->video_embed_url }}" title="{{ $post->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:12px;"></iframe></div>
@endif
@if($post->thumbnail)<img src="{{ $post->thumbnail->url }}" alt="{{ $post->thumbnail->alt_text ?: $post->title }}" style="width:100%;max-height:320px;object-fit:cover;border-radius:12px;margin-bottom:18px;">@endif
@if($post->short_description)<p class="muted">{{ $post->short_description }}</p>@endif
<div style="white-space:pre-line;line-height:1.8;">{{ $post->long_description }}</div>
@if($post->tags)<div class="muted" style="margin-top:20px;">{{ __('Tags') }}: {{ implode(', ', $post->tags) }}</div>@endif
</div>
@endsection
