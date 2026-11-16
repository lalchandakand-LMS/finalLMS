@extends('layouts.app')
@section('title', __('Edit course category'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Edit course category') }}</h1></div><a class="btn secondary" href="{{ route('admin.course-categories.index') }}">{{ __('Back') }}</a></div>
<div class="panel" style="max-width:760px;">
<form method="POST" action="{{ route('admin.course-categories.update', $courseCategory) }}">@csrf @method('PUT')
<label>{{ __('Category name') }}<input name="name" maxlength="120" value="{{ old('name', $courseCategory->name) }}" required></label>
<label>{{ __('Slug') }}<input name="slug" maxlength="150" value="{{ old('slug', $courseCategory->slug) }}"></label>
<label>{{ __('Description') }}<textarea name="description" rows="4">{{ old('description', $courseCategory->description) }}</textarea></label>
<label>{{ __('SEO title') }}<input name="seo_title" maxlength="180" value="{{ old('seo_title', $courseCategory->seo_title) }}"></label>
<label>{{ __('SEO description') }}<textarea name="seo_description" maxlength="320">{{ old('seo_description', $courseCategory->seo_description) }}</textarea></label>
<label>{{ __('Sort order') }}<input type="number" name="sort_order" min="0" value="{{ old('sort_order', $courseCategory->sort_order) }}"></label>
<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $courseCategory->is_visible)) style="width:auto;">{{ __('Visible on website') }}</label>
<button class="btn primary" type="submit">{{ __('Save category') }}</button>
</form></div>
@endsection
