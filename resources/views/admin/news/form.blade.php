@extends('layouts.app')
@section('title', $post->exists ? __('Edit article') : __('New article'))
@section('content')
<div class="page-header"><h1>{{ $post->exists ? __('Edit article') : __('Create article') }}</h1><a class="btn secondary" href="{{ route('admin.news.index') }}">{{ __('Back') }}</a></div>
<div class="panel"><form method="POST" action="{{ $post->exists ? route('admin.news.update', $post) : route('admin.news.store') }}">@csrf @if($post->exists) @method('PUT') @endif
<label>{{ __('Title') }}<input name="title" maxlength="180" value="{{ old('title', $post->title) }}" required></label>
<label>{{ __('URL slug (optional)') }}<input name="slug" maxlength="200" value="{{ old('slug', $post->slug) }}"></label>
<label>{{ __('Short summary') }}<textarea name="excerpt" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea></label>
<label>Course / Post category<input name="category" maxlength="120" value="{{ old('category', $post->category) }}" placeholder="Spoken English, Grammar, IELTS..."></label>
<label>Tags (comma separated)<input name="tags" maxlength="2000" value="{{ old('tags', is_array($post->tags) ? implode(', ', $post->tags) : '') }}" placeholder="speaking, grammar, vocabulary"></label>
<label>Video URL (YouTube / Vimeo)<input type="url" name="video_url" maxlength="2048" value="{{ old('video_url', $post->video_url) }}"></label>
<label>{{ __('Article body') }}<textarea name="body" rows="16" maxlength="100000" required>{{ old('body', $post->body) }}</textarea></label>
<label>{{ __('Cover image') }}<select name="cover_media_id"><option value="">{{ __('No cover') }}</option>@foreach($media as $asset)@if(str_starts_with($asset->mime_type, 'image/'))<option value="{{ $asset->id }}" @selected(old('cover_media_id', $post->cover_media_id) == $asset->id)>{{ $asset->original_name }}</option>@endif @endforeach</select></label>
<label>{{ __('Status') }}<select name="status"><option value="draft" @selected(old('status', $post->status ?: 'draft') === 'draft')>{{ __('Draft') }}</option><option value="published" @selected(old('status', $post->status) === 'published')>{{ __('Published') }}</option></select></label>
<label>{{ __('Publish date/time (optional)') }}<input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></label>
<label>{{ __('SEO title') }}<input name="seo_title" maxlength="255" value="{{ old('seo_title', $post->seo_title) }}"></label>
<label>{{ __('SEO description') }}<textarea name="seo_description" rows="2" maxlength="320">{{ old('seo_description', $post->seo_description) }}</textarea></label>
<button class="btn primary" style="margin-top:16px;">{{ __('Save article') }}</button>
</form></div>
@endsection