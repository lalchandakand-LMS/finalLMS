@extends('layouts.app')
@section('title', __('Course content studio'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course content studio') }}</h1><p class="muted">{{ __('Create reusable lessons and posts, then assemble them into course curricula.') }}</p></div><a class="btn primary" href="{{ route('admin.course-content-posts.create') }}">{{ __('Create content post') }}</a></div>
<div class="panel" style="margin-bottom:18px;"><form method="GET" class="grid grid-3">
<label>{{ __('Search') }}<input name="q" value="{{ request('q') }}" placeholder="{{ __('Title or description') }}"></label>
<label>{{ __('Category') }}<select name="category"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>{{ __('Status') }}<select name="status"><option value="">{{ __('All statuses') }}</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label>
<div><button class="btn secondary" type="submit">{{ __('Filter') }}</button> <a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Reset') }}</a></div>
</form></div>
<div class="panel"><div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
<thead><tr><th>{{ __('Content') }}</th><th>{{ __('Categories') }}</th><th>{{ __('Status') }}</th><th>{{ __('Author') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($posts as $post)
<tr style="border-top:1px solid var(--border);"><td style="padding:12px;">@if($post->thumbnail)<img src="{{ $post->thumbnail->url }}" alt="{{ $post->thumbnail->alt_text ?: $post->title }}" style="width:76px;height:48px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px;">@endif<strong>{{ $post->title }}</strong><div class="muted">{{ $post->slug }}</div></td>
<td>{{ $post->categories->pluck('name')->join(', ') ?: '—' }}</td><td><span class="badge {{ $post->status === 'published' ? 'success' : 'warning' }}">{{ __(ucfirst($post->status)) }}</span></td><td>{{ $post->author?->name }}</td>
<td><a class="btn secondary" href="{{ route('admin.course-content-posts.edit', $post) }}">{{ __('Edit') }}</a>
<form method="POST" action="{{ route('admin.course-content-posts.destroy', $post) }}" style="display:inline;" onsubmit="return confirm('{{ __('Delete this post?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete') }}</button></form></td></tr>
@empty<tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No content posts found.') }}</td></tr>@endforelse
</tbody></table></div>{{ $posts->links() }}</div>
@endsection
