@extends('layouts.app')
@section('title', $post->seo_title ?: $post->title)
@section('content')
<article class="panel" style="max-width:900px;margin:auto;">
@if($post->cover)<img src="{{ $post->cover->url }}" alt="{{ $post->cover->alt_text }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:14px;">@endif
<h1>{{ $post->title }}</h1><p class="muted">{{ $post->published_at->format('d M Y') }} · {{ $post->author->name }}</p>
@if($post->excerpt)<p><strong>{{ $post->excerpt }}</strong></p>@endif
<div style="white-space:pre-line;line-height:1.8;">{{ $post->body }}</div>
<div class="meta-row"><span class="badge info">{{ number_format($post->views_count) }} {{ __('views') }}</span><span class="muted">{{ $comments->total() }} {{ __('comments') }}</span></div>
<section style="margin-top:28px;border-top:1px solid var(--border);padding-top:20px;">
<h2>{{ __('Comments & discussion') }}</h2>
@auth
<form method="POST" action="{{ route('news.comments.store', $post->slug) }}" style="margin-bottom:20px;">@csrf
<label>{{ __('Write a comment') }}<textarea name="body" minlength="2" maxlength="2000" required></textarea></label><button class="btn primary" type="submit">{{ __('Submit comment') }}</button><small class="muted">{{ __('Comments appear after admin approval.') }}</small>
</form>
@else<p class="muted"><a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to comment.') }}</p>@endauth
@forelse($comments as $comment)<div class="course-item" style="margin-bottom:10px;"><strong>{{ $comment->author?->name ?? __('User') }}</strong><small class="muted"> · {{ $comment->created_at->format('d M Y') }}</small><p style="margin-bottom:0;">{{ $comment->body }}</p></div>
@empty<p class="muted">{{ __('No approved comments yet.') }}</p>@endforelse
{{ $comments->links() }}
</section>

<p style="margin-top:24px;"><a href="{{ route('news.index') }}" class="btn secondary">{{ __('All news') }}</a></p>
</article>
@endsection