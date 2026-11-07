@extends('layouts.app')

@section('title', isset($course) ? __('Edit course') . ' | ' . config('app.name') : __('Create course') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin') }}</span>
            <h1 style="margin: 12px 0 0;">{{ isset($course) ? __('Edit course') : __('Create course') }}</h1>
        </div>
    </div>

    <div class="panel" style="max-width: 800px; margin: 0 auto;">
        <form method="POST" action="{{ isset($course) ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
            @csrf
            @if(isset($course))
                @method('PUT')
            @endif

            <label>
                {{ __('Title') }}
                <input type="text" name="title" value="{{ old('title', $course?->title ?? '') }}" maxlength="255" required>
            </label>

            <label>
                {{ __('Description') }}
                <textarea name="description" rows="6" required>{{ old('description', $course?->description ?? '') }}</textarea>
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
