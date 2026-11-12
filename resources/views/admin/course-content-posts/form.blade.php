@extends('layouts.app')
@section('title', $post->exists ? __('Edit content post') : __('Create content post'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ $post->exists ? __('Edit content post') : __('Create content post') }}</h1></div><a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Back to studio') }}</a></div>
<div class="panel" style="max-width:900px;">
<form method="POST" action="{{ $post->exists ? route('admin.course-content-posts.update', $post) : route('admin.course-content-posts.store') }}">@csrf @if($post->exists)@method('PUT')@endif
<label>{{ __('Title') }}<input name="title" maxlength="180" value="{{ old('title', $post->title) }}" required></label>
<label>{{ __('Slug') }}<input name="slug" maxlength="200" value="{{ old('slug', $post->slug) }}" placeholder="{{ __('Generated from title if empty') }}"></label>
<label>{{ __('Short description') }}<textarea name="short_description" maxlength="500" rows="2">{{ old('short_description', $post->short_description) }}</textarea></label>
<label>{{ __('Long description / lesson content') }}<textarea name="long_description" maxlength="100000" rows="10" required>{{ old('long_description', $post->long_description) }}</textarea></label>
<label>{{ __('YouTube or Vimeo video URL') }}<input type="url" name="video_url" maxlength="500" value="{{ old('video_url', $post->video_url) }}" placeholder="https://youtu.be/..."></label>
<label>{{ __('Thumbnail image') }}<select name="thumbnail_media_id"><option value="">{{ __('No thumbnail') }}</option>@foreach($media as $item)<option value="{{ $item->id }}" @selected((string)old('thumbnail_media_id', $post->thumbnail_media_id) === (string)$item->id)>{{ $item->original_name }}</option>@endforeach</select></label>
<label>{{ __('Categories (select one or more)') }}</label>
<div class="grid grid-3" style="margin-bottom:16px;">@foreach($categories as $category)<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $post->exists ? $post->categories->pluck('id')->all() : []))) style="width:auto;">{{ $category->name }}</label>@endforeach</div>
<label>{{ __('Tags (comma separated)') }}<input name="tags" maxlength="1000" value="{{ old('tags', implode(', ', $post->tags ?? [])) }}" placeholder="grammar, speaking, beginner"></label>
<label>{{ __('SEO title') }}<input name="seo_title" maxlength="180" value="{{ old('seo_title', $post->seo_title) }}"></label>
<label>{{ __('SEO description') }}<textarea name="seo_description" maxlength="320">{{ old('seo_description', $post->seo_description) }}</textarea></label>
<div class="grid grid-2"><label>{{ __('Status') }}<select name="status" required>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $post->status ?: 'draft') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label><label>{{ __('Publish date/time (optional)') }}<input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></label></div>
<div style="display:flex;gap:10px;margin-top:18px;"><button class="btn primary" type="submit">{{ __('Save content post') }}</button><a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Cancel') }}</a></div>
</form></div>
@endsection
