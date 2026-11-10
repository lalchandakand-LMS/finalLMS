@extends('layouts.app')

@section('title', __('Course categories') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course categories') }}</h1><p class="muted">{{ __('Manage the categories available to courses and reusable content posts.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.courses.index') }}">{{ __('Course management') }}</a>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2>{{ __('Create category') }}</h2>
    <form method="POST" action="{{ route('admin.course-categories.store') }}">
        @csrf
        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <label>{{ __('Category name') }}<input name="name" maxlength="120" required></label>
            <label>{{ __('URL slug (optional)') }}<input name="slug" maxlength="150"></label>
            <label>{{ __('Sort order') }}<input type="number" name="sort_order" min="0" max="100000" value="0"></label>
            <label>{{ __('SEO title') }}<input name="seo_title" maxlength="255"></label>
            <label style="grid-column:1/-1;">{{ __('Description') }}<textarea name="description" rows="2" maxlength="5000"></textarea></label>
            <label style="grid-column:1/-1;">{{ __('SEO description') }}<textarea name="seo_description" rows="2" maxlength="320"></textarea></label>
            <label><input type="checkbox" name="is_active" value="1" checked> {{ __('Active') }}</label>
        </div>
        <button class="btn primary" style="margin-top:12px;">{{ __('Create category') }}</button>
    </form>
</div>

<div class="panel">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th style="text-align:left;padding:10px;">{{ __('Category') }}</th><th>{{ __('Courses') }}</th><th>{{ __('Content posts') }}</th><th>{{ __('Status') }}</th><th>{{ __('Edit') }}</th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:12px 10px;min-width:220px;">
                        <strong>{{ $category->name }}</strong><div class="muted">{{ $category->slug }}</div>
                        <form method="POST" action="{{ route('admin.course-categories.update', $category) }}" style="margin-top:8px;display:grid;gap:6px;">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $category->name }}" maxlength="120" required>
                            <input name="slug" value="{{ $category->slug }}" maxlength="150">
                            <input name="sort_order" type="number" min="0" max="100000" value="{{ $category->sort_order }}">
                            <textarea name="description" rows="2">{{ $category->description }}</textarea>
                            <input name="seo_title" value="{{ $category->seo_title }}" placeholder="{{ __('SEO title') }}">
                            <textarea name="seo_description" rows="2" maxlength="320" placeholder="{{ __('SEO description') }}">{{ $category->seo_description }}</textarea>
                            <label><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> {{ __('Active') }}</label>
                            <button class="btn secondary">{{ __('Save changes') }}</button>
                        </form>
                    </td>
                    <td style="text-align:center;">{{ $category->courses_count }}</td>
                    <td style="text-align:center;">{{ $category->posts_count }}</td>
                    <td style="text-align:center;">{{ $category->is_active ? __('Active') : __('Hidden') }}</td>
                    <td style="text-align:center;">
                        <form method="POST" action="{{ route('admin.course-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete this category? Linked courses and posts will remain.') }}')">
                            @csrf @method('DELETE')
                            <button class="btn secondary">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No course categories yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $categories->links() }}
</div>
@endsection
