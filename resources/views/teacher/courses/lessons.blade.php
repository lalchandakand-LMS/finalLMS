@extends('layouts.app')
@section('title', __('Course lessons') . ' | ' . config('app.name'))
@section('content')
@php($lessonRoutePrefix = auth()->user()->isAdmin() ? 'admin.courses.lessons' : 'teacher.courses.lessons')
@php($sectionRoutePrefix = auth()->user()->isAdmin() ? 'admin.courses.sections' : 'teacher.courses.sections')
<div class="page-header"><div><span class="badge info">{{ __('Teacher portal') }}</span><h1 style="margin:12px 0 0;">{{ $course->title }} — {{ __('Lessons') }}</h1></div><a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a></div>
<div class="panel" style="margin-top:18px;">
    <h2>Course Builder — Sections / Modules</h2>
    <form method="POST" action="{{ route($sectionRoutePrefix . '.store', $course) }}" class="grid grid-2">
        @csrf
        <label>Section title<input name="title" maxlength="180" required placeholder="Module 1 — Introduction"></label>
        <label>Position<input type="number" name="position" min="1" value="{{ $course->sections->count()+1 }}" required></label>
        <label>Description<textarea name="description" rows="2"></textarea></label>
        <label>Status<select name="status"><option value="published">Published</option><option value="draft">Draft</option></select></label>
        <button class="btn primary" type="submit">Add section</button>
    </form>
    @foreach($course->sections as $section)
    <details style="padding:12px 0;border-top:1px solid var(--border);">
        <summary><strong>{{ $section->position }}. {{ $section->title }}</strong> — {{ $section->lessons->count() }} lessons ({{ $section->status }})</summary>
        <form method="POST" action="{{ route($sectionRoutePrefix . '.update', [$course,$section]) }}" class="grid grid-2" style="margin-top:10px;">
            @csrf @method('PUT')
            <label>Title<input name="title" value="{{ $section->title }}" required></label>
            <label>Position<input type="number" name="position" min="1" value="{{ $section->position }}" required></label>
            <label>Description<textarea name="description">{{ $section->description }}</textarea></label>
            <label>Status<select name="status"><option value="published" @selected($section->status==='published')>Published</option><option value="draft" @selected($section->status==='draft')>Draft</option></select></label>
            <button class="btn primary">Save section</button>
        </form>
        <form method="POST" action="{{ route($sectionRoutePrefix . '.destroy', [$course,$section]) }}" style="margin-top:8px;">@csrf @method('DELETE')<button class="btn">Delete section (lessons stay)</button></form>
    </details>
    @endforeach
</div>
<div class="panel">
    <h2>{{ __('Add lesson') }}</h2>
    <form method="POST" action="{{ route($lessonRoutePrefix . '.store', $course) }}" class="grid grid-2">
        @csrf
        <label>Section<select name="section_id"><option value="">Unsectioned lesson</option>@foreach($course->sections as $section)<option value="{{ $section->id }}">{{ $section->position }}. {{ $section->title }}</option>@endforeach</select></label>
        <label>{{ __('Title') }}<input name="title" maxlength="255" required></label>
        <label>{{ __('Duration (min)') }}<input type="number" name="duration_minutes" min="1" max="600" value="30" required></label>
        <label>{{ __('Order') }}<input type="number" name="order" min="1" value="{{ $course->lessons->count()+1 }}" required></label>
        <label>{{ __('YouTube or Vimeo URL') }}<input type="url" name="video_url" maxlength="2048" placeholder="https://youtu.be/..."></label>
        <label style="grid-column:1/-1;">{{ __('Lesson content') }}<textarea name="content" required></textarea></label>
        <label><input type="checkbox" name="is_preview" value="1"> {{ __('Free preview') }}</label>
        <button class="btn primary" type="submit">{{ __('Add lesson') }}</button>
    </form>
</div>
<div class="panel" style="margin-top:20px;">
<h2>{{ __('Manage lessons') }}</h2>
@forelse($course->lessons as $lesson)
<details style="padding:14px 0;border-bottom:1px solid var(--border);">
    <summary><strong>{{ $lesson->order }}. {{ $lesson->title }}</strong> · {{ $lesson->duration_minutes }} {{ __('min') }}</summary>
    <form method="POST" action="{{ route($lessonRoutePrefix . '.update', [$course,$lesson]) }}" class="grid grid-2" style="margin-top:14px;">
        @csrf @method('PUT')
        <label>Section<select name="section_id"><option value="">Unsectioned lesson</option>@foreach($course->sections as $section)<option value="{{ $section->id }}" @selected($lesson->section_id == $section->id)>{{ $section->position }}. {{ $section->title }}</option>@endforeach</select></label>
        <label>{{ __('Title') }}<input name="title" value="{{ $lesson->title }}" maxlength="255" required></label>
        <label>{{ __('Duration (min)') }}<input type="number" name="duration_minutes" value="{{ $lesson->duration_minutes }}" min="1" max="600" required></label>
        <label>{{ __('Order') }}<input type="number" name="order" value="{{ $lesson->order }}" min="1" required></label>
        <label>{{ __('Video URL') }}<input type="url" name="video_url" value="{{ $lesson->video_url }}" maxlength="2048"></label>
        <label style="grid-column:1/-1;">{{ __('Content') }}<textarea name="content" required>{{ $lesson->content }}</textarea></label>
        <label><input type="checkbox" name="is_preview" value="1" @checked($lesson->is_preview)> {{ __('Free preview') }}</label>
        <button class="btn primary" type="submit">{{ __('Save changes') }}</button>
    </form>
    <form method="POST" action="{{ route($lessonRoutePrefix . '.destroy', [$course,$lesson]) }}" style="margin-top:10px;" onsubmit="return confirm('{{ __('Delete this lesson? Student progress for this lesson will also be removed if the database cascade is configured.') }}')">
        @csrf @method('DELETE')<button class="btn" style="background:#b42318;color:#fff;">{{ __('Delete lesson') }}</button>
    </form>
</details>
@empty
<p class="muted">{{ __('No lessons have been added yet.') }}</p>
@endforelse
</div>
<div class="panel" style="margin-top:20px;">
<h2>{{ __('Student lesson progress') }}</h2>
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;"><thead><tr><th>{{ __('Student') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Completed lessons') }}</th></tr></thead><tbody>
@forelse($students as $enrollment)
<tr style="border-top:1px solid var(--border);"><td>{{ $enrollment->user->name }}</td><td>{{ $enrollment->progress_percent }}%<div style="height:7px;background:#e5e7eb;border-radius:9px;max-width:220px;"><div style="height:7px;width:{{ $enrollment->progress_percent }}%;background:#2563eb;border-radius:9px;"></div></div></td><td>{{ $enrollment->completed_lessons_count }} / {{ $course->lessons->count() }}</td></tr>
@empty
<tr><td colspan="3" class="muted">{{ __('No enrolled students yet.') }}</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
