@extends('layouts.app')
@section('title', __('Blog & News'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Blog & News') }}</h1><p class="muted">{{ __('Create articles, announcements and news updates.') }}</p></div><a class="btn primary" href="{{ route('admin.news.create') }}">{{ __('New article') }}</a></div>
<div class="panel"><div class="course-list">
@forelse($posts as $post)
<div class="course-item"><div class="page-header" style="margin:0;"><div><h3>{{ $post->title }}</h3><p class="muted">{{ $post->slug }} · {{ $post->author->name }} · {{ ucfirst($post->status) }}</p></div><div><a class="btn secondary" href="{{ route('admin.news.edit', $post) }}">{{ __('Edit') }}</a></div></div>
<form method="POST" action="{{ route('admin.news.destroy', $post) }}" onsubmit="return confirm('{{ __('Delete this article?') }}')">@csrf @method('DELETE')<button class="btn secondary">{{ __('Delete') }}</button></form></div>
@empty<p class="muted">{{ __('No articles created.') }}</p>@endforelse
</div>{{ $posts->links() }}</div>
@endsection