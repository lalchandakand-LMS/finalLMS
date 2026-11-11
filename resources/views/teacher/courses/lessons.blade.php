@extends('layouts.app')
@section('title', __('Course lessons') . ' | ' . config('app.name'))
@section('content')
@php($lessonRoutePrefix = auth()->user()->isAdmin() ? 'admin.courses.lessons' : 'teacher.courses.lessons')
<div class="page-header"><div><span class="badge info">{{ __('Teacher portal') }}</span><h1 style="margin:12px 0 0;">{{ $course->title }} — {{ __('Lessons') }}</h1></div><a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a></div>
<div class="panel">
    <h2>{{ __('Add lesson') }}</h2>
    <form method="POST" enctype="multipart/form-data" action="{{ route($lessonRoutePrefix . '.store', $course) }}" class="grid grid-2">
        @csrf
        <label>{{ __('Title') }}<input name="title" maxlength="255" required></label>
        <label>{{ __('Duration (min)') }}<input type="number" name="duration_minutes" min="1" max="600" value="30" required></label>
        <label>{{ __('Order') }}<input type="number" name="order" min="1" value="{{ $course->lessons->count()+1 }}" required></label>
        <label>{{ __('YouTube or Vimeo URL') }}<input type="url" name="video_url" maxlength="2048" placeholder="https://youtu.be/..."></label>
        <label>{{ __('PDF / Notes / Image (max 20 MB)') }}<input type="file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png"></label>
        <label>{{ __('Video upload (MP4/WebM/OGG, max 50 MB)') }}<input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg"></label>
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
    <form method="POST" enctype="multipart/form-data" action="{{ route($lessonRoutePrefix . '.update', [$course,$lesson]) }}" class="grid grid-2" style="margin-top:14px;">
        @csrf @method('PUT')
        <label>{{ __('Title') }}<input name="title" value="{{ $lesson->title }}" maxlength="255" required></label>
        <label>{{ __('Duration (min)') }}<input type="number" name="duration_minutes" value="{{ $lesson->duration_minutes }}" min="1" max="600" required></label>
        <label>{{ __('Order') }}<input type="number" name="order" value="{{ $lesson->order }}" min="1" required></label>
        <label>{{ __('Video URL') }}<input type="url" name="video_url" value="{{ $lesson->video_url }}" maxlength="2048"></label>
        <label>{{ __('Replace PDF / Notes / Image (max 20 MB)') }}<input type="file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png"></label>
        <label>{{ __('Replace video upload (MP4/WebM/OGG, max 50 MB)') }}<input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg"></label>
        @if($lesson->attachment_path)<label><input type="checkbox" name="remove_attachment" value="1"> {{ __('Remove current attachment') }} ({{ $lesson->attachment_name }})</label>@endif
        @if($lesson->video_path)<label><input type="checkbox" name="remove_video" value="1"> {{ __('Remove current uploaded video') }} ({{ $lesson->video_name }})</label>@endif
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
