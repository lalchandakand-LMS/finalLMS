@extends('layouts.app')

@section('title', isset($course) ? 'Edit Course | LMS Academy' : 'Create Course | LMS Academy')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">{{ isset($course) ? 'Edit course' : 'Create course' }}</h1>
        </div>
    </div>

    <div class="panel" style="max-width: 800px; margin: 0 auto;">
        <form method="POST" action="{{ isset($course) ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
            @csrf
            @if(isset($course))
                @method('PUT')
            @endif

            <label>
                Title
                <input type="text" name="title" value="{{ old('title', $course->title ?? '') }}" required>
            </label>

            <label>
                Description
                <textarea name="description" rows="6" required>{{ old('description', $course->description ?? '') }}</textarea>
            </label>

            <label>
                Teacher
                <select name="teacher_id" required>
                    <option value="">Select a teacher</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" {{ old('teacher_id', $course->teacher_id ?? '') == $teacher->id ? 'selected' : '' }}>
                            {{ $teacher->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Price (BDT)
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $course->price ?? 0) }}" required>
            </label>

            <label>
                Status
                <select name="status" required>
                    <option value="published" {{ old('status', $course->status ?? '') === 'published' ? 'selected' : '' }}>Published</option>
                    <option value="draft" {{ old('status', $course->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="archived" {{ old('status', $course->status ?? '') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </label>

            <div style="display:flex; gap:12px; margin-top:20px;">
                <button type="submit" class="btn primary">Save course</button>
                <a href="{{ route('admin.courses.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
