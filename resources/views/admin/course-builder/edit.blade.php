@extends('layouts.app')
@section('title', __('Course curriculum builder'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course curriculum builder') }}</h1><p class="muted">{{ $course->title }} · {{ __('Select and order published content posts for this course.') }}</p></div><a class="btn secondary" href="{{ route('admin.courses.index') }}">{{ __('Back to courses') }}</a></div>
<div class="panel">
<form method="POST" action="{{ route('admin.course-builder.update', $course) }}">@csrf @method('PUT')
@php($current = $course->curriculumItems->keyBy('course_content_post_id'))
@if($availablePosts->isEmpty())<p class="muted">{{ __('Publish content posts in the Content Studio before adding them to a course.') }}</p>@else
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
<thead><tr><th>{{ __('Include') }}</th><th>{{ __('Content post') }}</th><th>{{ __('Section / module') }}</th><th>{{ __('Order') }}</th><th>{{ __('Duration (min)') }}</th><th>{{ __('Free preview') }}</th></tr></thead>
<tbody>
@foreach($availablePosts as $post)
@php($item = $current->get($post->id))
<tr style="border-top:1px solid var(--border);"><td style="padding:12px;"><input type="checkbox" name="post_ids[]" value="{{ $post->id }}" @checked($item) style="width:auto;"></td>
<td><strong>{{ $post->title }}</strong><div class="muted">{{ $post->categories->pluck('name')->join(', ') }}</div></td>
<td><input name="section_titles[{{ $post->id }}]" maxlength="120" value="{{ old('section_titles.'.$post->id, $item?->section_title) }}" placeholder="{{ __('e.g. Module 1') }}"></td>
<td><input type="number" name="positions[{{ $post->id }}]" min="0" max="100000" value="{{ old('positions.'.$post->id, $item?->position ?? (($loop->iteration) * 10)) }}" style="width:90px;"></td>
<td><input type="number" name="durations[{{ $post->id }}]" min="1" max="600" value="{{ old('durations.'.$post->id, $item?->duration_minutes) }}" style="width:90px;"></td>
<td><input type="checkbox" name="previews[{{ $post->id }}]" value="1" @checked(old('previews.'.$post->id, $item?->is_preview ?? false)) style="width:auto;"></td></tr>
@endforeach
</tbody></table></div>
@endif
<button class="btn primary" type="submit" style="margin-top:18px;">{{ __('Save course curriculum') }}</button>
</form></div>
@endsection
