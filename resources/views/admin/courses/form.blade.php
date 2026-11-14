@extends('layouts.app')

@section('title', isset($course) ? __('Edit course') . ' | ' . config('app.name') : __('Create course') . ' | ' . config('app.name'))

@section('content')
    @php($selectedCategoryIds = collect(old('category_ids', $course?->categories?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all())
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin') }}</span>
            <h1 style="margin: 12px 0 0;">{{ isset($course) ? __('Edit course') : __('Create course') }}</h1>
        </div>
    </div>

    <div class="panel" style="max-width: 800px; margin: 0 auto;">
        <form method="POST" enctype="multipart/form-data" action="{{ isset($course) ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
            @csrf
            @if(isset($course))
                @method('PUT')
            @endif

            <label>
                {{ __('Title') }}
                <input type="text" name="title" value="{{ old('title', $course?->title ?? '') }}" maxlength="255" required>
            </label>

            <label>
                {{ __('Course image') }}
                @if($course?->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" style="display:block;width:220px;max-width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:12px;margin:8px 0;">@endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="muted">{{ __('JPG, PNG or WebP; maximum 5 MB. Leave empty to keep the current image.') }}</small>
            </label>

            <label>
                {{ __('Description') }}
                <textarea name="description" rows="6" required>{{ old('description', $course?->description ?? '') }}</textarea>
            </label>

            <label>
                {{ __('Short description') }}
                <textarea name="short_description" rows="2" maxlength="500">{{ old('short_description', $course?->short_description ?? '') }}</textarea>
            </label>

            <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;">
                <legend>{{ __('Course categories') }}</legend>
                @forelse($categories as $category)
                    <label style="display:flex;align-items:center;gap:8px;margin:8px 0;">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategoryIds, true))>
                        <span>{{ $category->name }}</span>
                    </label>
                @empty
                    <p class="muted">{{ __('Create a course category first to organize this course.') }}</p>
                @endforelse
            </fieldset>

            <label>
                {{ __('Tags (comma separated)') }}
                <input type="text" name="tags" maxlength="2000" value="{{ old('tags', implode(', ', $course?->tags ?? [])) }}" placeholder="{{ __('speaking, grammar, IELTS') }}">
            </label>

            <div class="grid grid-2">
                <label>
                    {{ __('Level') }}
                    <select name="level">
                        <option value="">{{ __('Select level') }}</option>
                        @foreach(['Beginner', 'Elementary', 'Intermediate', 'Upper-Intermediate', 'Advanced', 'All levels'] as $level)
                            <option value="{{ $level }}" @selected(old('level', $course?->level ?? '') === $level)>{{ __($level) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    {{ __('Language') }}
                    <select name="language">
                        <option value="">{{ __('Select language') }}</option>
                        @foreach(['Bangla', 'English', 'Bangla & English'] as $language)
                            <option value="{{ $language }}" @selected(old('language', $course?->language ?? '') === $language)>{{ __($language) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label>
                {{ __('Duration (minutes)') }}
                <input type="number" name="duration_minutes" min="1" max="100000" value="{{ old('duration_minutes', $course?->duration_minutes ?? '') }}">
            </label>

            <label>
                {{ __('Learning outcomes') }}
                <textarea name="learning_outcomes" rows="4" maxlength="10000">{{ old('learning_outcomes', $course?->learning_outcomes ?? '') }}</textarea>
            </label>

            <label>
                {{ __('Requirements / prerequisites') }}
                <textarea name="requirements" rows="3" maxlength="10000">{{ old('requirements', $course?->requirements ?? '') }}</textarea>
            </label>

            <label>
                {{ __('SEO title') }}
                <input type="text" name="seo_title" maxlength="255" value="{{ old('seo_title', $course?->seo_title ?? '') }}">
            </label>

            <label>
                {{ __('SEO description') }}
                <textarea name="seo_description" rows="2" maxlength="320">{{ old('seo_description', $course?->seo_description ?? '') }}</textarea>
            </label>

            <label>
                {{ __('Teacher') }}
                <select name="teacher_id" required>
                    <option value="">{{ __('Select a teacher') }}</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" {{ (string) old('teacher_id', $course?->teacher_id ?? '') === (string) $teacher->id ? 'selected' : '' }}>
                            {{ $teacher->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                {{ __('Price (BDT)') }}
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $course?->price ?? 0) }}" required>
            </label>

            <label>
                {{ __('Status') }}
                <select name="status" required>
                    <option value="published" {{ old('status', $course?->status ?? 'draft') === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                    <option value="draft" {{ old('status', $course?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                    <option value="archived" {{ old('status', $course?->status ?? 'draft') === 'archived' ? 'selected' : '' }}>{{ __('Archived') }}</option>
                </select>
            </label>

            <div style="display:flex; gap:12px; margin-top:20px;">
                <button type="submit" class="btn primary">{{ __('Save course') }}</button>
                <a href="{{ route('admin.courses.index') }}" class="btn secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
