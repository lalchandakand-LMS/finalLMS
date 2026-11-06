@extends('layouts.app')
@section('title', 'Blog & News')
@section('content')
<div class="page-header"><div><span class="badge info">Admin</span><h1>Blog & News</h1><p class="muted">Create articles, announcements and news updates.</p></div><a class="btn primary" href="{{ route('admin.news.create') }}">New article</a></div>
<div class="panel"><div class="course-list">
@forelse($posts as $post)
<div class="course-item"><div class="page-header" style="margin:0;"><div><h3>{{ $post->title }}</h3><p class="muted">{{ $post->slug }} · {{ $post->author->name }} · {{ ucfirst($post->status) }}</p></div><div><a class="btn secondary" href="{{ route('admin.news.edit', $post) }}">Edit</a></div></div>
<form method="POST" action="{{ route('admin.news.destroy', $post) }}" onsubmit="return confirm('Delete this article?')">@csrf @method('DELETE')<button class="btn secondary">Delete</button></form></div>
@empty<p class="muted">No articles created.</p>@endforelse
</div>{{ $posts->links() }}</div>
@endsection