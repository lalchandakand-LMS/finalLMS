@extends('layouts.app')

@section('title', isset($course) ? __('Edit course') . ' | ' . config('app.name') : __('Create course') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1 style="margin:12px 0 0;">{{ isset($course) ? __('Edit course') : __('Create course') }}</h1></div>
    <a href="{{ route('admin.course-categories.index') }}" class="btn secondary">{{ __('Manage categories') }}</a>
</div>

<div class="panel" style="max-width:960px;margin:0 auto;">
    <form method="POST" enctype="multipart/form-data" action="{{ isset($course) ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
        @csrf
        @if(isset($course)) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
            <label style="grid-column:1/-1;">{{ __('Course title') }}<input type="text" name="title" value="{{ old('title', $course?->title ?? '') }}" maxlength="255" required></label>
            <label style="grid-column:1/-1;">{{ __('Short description') }}<textarea name="short_description" rows="2" maxlength="500" placeholder="{{ __('A concise summary shown on course cards and search results.') }}">{{ old('short_description', $course?->short_description ?? '') }}</textarea></label>
            <label style="grid-column:1/-1;">{{ __('Long description') }}<textarea name="description" rows="8" maxlength="100000" required>{{ old('description', $course?->description ?? '') }}</textarea></label>

            <label>{{ __('Teacher') }}<select name="teacher_id" required><option value="">{{ __('Select a teacher') }}</option>
                @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $course?->teacher_id ?? '') === (string) $teacher->id)>{{ $teacher->name }}</option>@endforeach
            </select></label>
            <label>{{ __('Price (BDT)') }}<input type="number" step="0.01" min="0" max="999999999" name="price" value="{{ old('price', $course?->price ?? 0) }}" required></label>

            <label style="grid-column:1/-1;">{{ __('Course categories') }}<select name="category_ids[]" multiple size="5" style="min-height:125px;">
                @foreach($categories as $category)<option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $course?->categories->pluck('id')->all() ?? [])))>{{ $category->name }}</option>@endforeach
            </select><small class="muted">{{ __('Select one or more categories. Create categories from Manage categories.') }}</small></label>

            <label>{{ __('Tags (comma separated)') }}<input name="tags" maxlength="2000" value="{{ old('tags', implode(', ', $course?->tags ?? [])) }}" placeholder="spoken English, grammar, IELTS"></label>
            <label>{{ __('Level') }}<select name="level"><option value="">{{ __('Select level') }}</option>
                @foreach(['Beginner','Elementary','Intermediate','Upper-Intermediate','Advanced','All levels'] as $level)<option value="{{ $level }}" @selected(old('level', $course?->level) === $level)>{{ __($level) }}</option>@endforeach
            </select></label>
            <label>{{ __('Language') }}<select name="language"><option value="">{{ __('Select language') }}</option>
                @foreach(['Bangla','English','Bangla & English'] as $language)<option value="{{ $language }}" @selected(old('language', $course?->language) === $language)>{{ __($language) }}</option>@endforeach
            </select></label>
            <label>{{ __('Duration (minutes)') }}<input type="number" name="duration_minutes" min="1" max="100000" value="{{ old('duration_minutes', $course?->duration_minutes) }}"></label>

            <label style="grid-column:1/-1;">{{ __('Learning outcomes') }}<textarea name="learning_outcomes" rows="3" maxlength="10000" placeholder="{{ __('What will students learn?') }}">{{ old('learning_outcomes', $course?->learning_outcomes ?? '') }}</textarea></label>
            <label style="grid-column:1/-1;">{{ __('Requirements / prerequisites') }}<textarea name="requirements" rows="3" maxlength="10000">{{ old('requirements', $course?->requirements ?? '') }}</textarea></label>

            <label style="grid-column:1/-1;">{{ __('Course image / thumbnail') }}
                @if($course?->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" style="display:block;width:220px;max-width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:12px;margin:8px 0;">@endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small class="muted">{{ __('JPG, PNG or WebP; maximum 5 MB. Leave empty to keep the current image.') }}</small>
            </label>

            <label>{{ __('SEO title') }}<input name="seo_title" maxlength="255" value="{{ old('seo_title', $course?->seo_title ?? '') }}"></label>
            <label style="grid-column:1/-1;">{{ __('SEO description') }}<textarea name="seo_description" rows="2" maxlength="320">{{ old('seo_description', $course?->seo_description ?? '') }}</textarea></label>
            <label>{{ __('Publication status') }}<select name="status" required>
                <option value="draft" @selected(old('status', $course?->status ?? 'draft') === 'draft')>{{ __('Draft') }}</option>
                <option value="published" @selected(old('status', $course?->status ?? 'draft') === 'published')>{{ __('Published') }}</option>
                <option value="archived" @selected(old('status', $course?->status ?? 'draft') === 'archived')>{{ __('Archived') }}</option>
            </select></label>
        </div>

        <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
            <button type="submit" class="btn primary">{{ __('Save course') }}</button>
            <a href="{{ route('admin.courses.index') }}" class="btn secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
