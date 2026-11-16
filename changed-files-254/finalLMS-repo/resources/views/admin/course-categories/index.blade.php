@extends('layouts.app')
@section('title', __('Course categories'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course categories') }}</h1><p class="muted">{{ __('Create and organize categories used by courses and course content posts.') }}</p></div></div>
<div class="panel" style="margin-bottom:20px;">
    <h2>{{ __('Create category') }}</h2>
    <form method="POST" action="{{ route('admin.course-categories.store') }}" class="grid grid-2">@csrf
        <label>{{ __('Category name') }}<input name="name" maxlength="120" required></label>
        <label>{{ __('Slug (optional)') }}<input name="slug" maxlength="150"></label>
        <label style="grid-column:1/-1;">{{ __('Description') }}<textarea name="description" rows="2"></textarea></label>
        <label>{{ __('SEO title') }}<input name="seo_title" maxlength="180"></label>
        <label>{{ __('SEO description') }}<input name="seo_description" maxlength="320"></label>
        <label>{{ __('Sort order') }}<input type="number" name="sort_order" min="0" value="0"></label>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_visible" value="1" checked style="width:auto;">{{ __('Visible on website') }}</label>
        <div style="grid-column:1/-1;"><button class="btn primary" type="submit">{{ __('Create category') }}</button></div>
    </form>
</div>
<div class="panel"><div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
<thead><tr><th>{{ __('Category') }}</th><th>{{ __('Courses') }}</th><th>{{ __('Posts') }}</th><th>{{ __('Visibility') }}</th><th>{{ __('Order') }}</th><th>{{ __('Actions') }}</th></tr></thead>
<tbody>
@forelse($categories as $category)
<tr style="border-top:1px solid var(--border);"><td style="padding:12px;"> <strong>{{ $category->name }}</strong><div class="muted">{{ $category->slug }}</div></td><td>{{ $category->courses_count }}</td><td>{{ $category->posts_count }}</td><td>{{ $category->is_visible ? __('Visible') : __('Hidden') }}</td><td>{{ $category->sort_order }}</td><td><a class="btn secondary" href="{{ route('admin.course-categories.edit', $category) }}">{{ __('Edit') }}</a>
<form method="POST" action="{{ route('admin.course-categories.destroy', $category) }}" style="display:inline;" onsubmit="return confirm('{{ __('Delete this category?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete') }}</button></form></td></tr>
@empty<tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No categories created yet.') }}</td></tr>@endforelse
</tbody></table></div>{{ $categories->links() }}</div>
@endsection
