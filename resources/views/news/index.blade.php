@extends('layouts.app')
@section('title', 'News & Updates')
@section('content')
<div class="page-header"><div><span class="badge info">Alom's English Center</span><h1>Blog & News</h1><p class="muted">Latest announcements, learning tips and center updates.</p></div></div>
<div class="grid grid-3">
@forelse($posts as $post)
<article class="card">@if($post->cover)<img src="{{ $post->cover->url }}" alt="{{ $post->cover->alt_text }}" style="width:100%;height:180px;object-fit:cover;border-radius:12px;">@endif
<h2>{{ $post->title }}</h2><p class="muted">{{ $post->published_at->format('d M Y') }} · {{ $post->author->name }}</p><p>{{ $post->excerpt }}</p><a class="btn secondary" href="{{ route('news.show', $post->slug) }}">Read more</a></article>
@empty<p class="muted">No news published yet.</p>@endforelse
</div><div style="margin-top:20px;">{{ $posts->links() }}</div>
@endsection