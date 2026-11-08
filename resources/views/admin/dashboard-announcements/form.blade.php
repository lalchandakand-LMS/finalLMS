@extends('layouts.app')
@section('title', $announcement->exists ? __('Edit announcement') : __('Create announcement'))
@section('content')
<div class="page-header"><div><h1>{{ $announcement->exists ? __('Edit dashboard announcement') : __('Create dashboard announcement') }}</h1><p class="muted">{{ __('The slide appears in the selected user dashboard and profile page.') }}</p></div><a class="btn secondary" href="{{ route('admin.dashboard-announcements.index') }}">{{ __('Back') }}</a></div>
<div class="panel"><form method="POST" action="{{ $announcement->exists ? route('admin.dashboard-announcements.update', $announcement) : route('admin.dashboard-announcements.store') }}">@csrf @if($announcement->exists)@method('PUT')@endif
<label>{{ __('Heading') }}<input name="title" maxlength="180" value="{{ old('title', $announcement->title) }}" required></label>
<label>{{ __('Message / details') }}<textarea name="body" rows="4" maxlength="3000">{{ old('body', $announcement->body) }}</textarea></label>
<div class="grid grid-2">
<label>{{ __('Content source') }}<select name="content_type" id="announcement-type">@foreach(['custom' => 'Custom notice / ad', 'course' => 'New course notification', 'post' => 'New post notification'] as $value => $label)<option value="{{ $value }}" @selected(old('content_type', $announcement->content_type) === $value)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Audience') }}<select name="audience">@foreach(['all' => 'All supported accounts', 'student' => 'Students', 'teacher' => 'Teachers', 'guardian' => 'Guardians'] as $value => $label)<option value="{{ $value }}" @selected(old('audience', $announcement->audience) === $value)>{{ __($label) }}</option>@endforeach</select></label>
</div>
<label id="course-choice">{{ __('Published course') }}<select name="course_id"><option value="">{{ __('Select course') }}</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id', $announcement->course_id) == $course->id)>{{ $course->title }}</option>@endforeach</select></label>
<label id="post-choice">{{ __('Published post') }}<select name="blog_post_id"><option value="">{{ __('Select post') }}</option>@foreach($posts as $post)<option value="{{ $post->id }}" @selected(old('blog_post_id', $announcement->blog_post_id) == $post->id)>{{ $post->title }}</option>@endforeach</select></label>
<label>{{ __('Slider banner image (Media Library)') }}<select name="image_media_id"><option value="">{{ __('No image') }}</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected(old('image_media_id', $announcement->image_media_id) == $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
<div class="grid grid-2"><label>{{ __('Button label') }}<input name="button_label" maxlength="60" value="{{ old('button_label', $announcement->button_label) }}" placeholder="{{ __('View course / Read post') }}"></label><label id="custom-url">{{ __('Custom button URL') }}<input name="button_url" maxlength="500" value="{{ old('button_url', $announcement->button_url) }}" placeholder="/courses or https://example.com"></label></div>
<div class="grid grid-2"><label>{{ __('Priority (higher first)') }}<input type="number" name="priority" min="0" max="10000" value="{{ old('priority', $announcement->priority ?? 0) }}"></label><label>{{ __('Start date/time') }}<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\\TH:i')) }}"></label></div>
<label>{{ __('End date/time') }}<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\\TH:i')) }}"></label>
<label style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $announcement->is_active))> {{ __('Activate immediately / according to schedule') }}</label>
<button class="btn primary" type="submit">{{ __('Save announcement') }}</button>
</form></div>
<script>
(function(){
 const type=document.getElementById('announcement-type');
 const course=document.getElementById('course-choice');
 const post=document.getElementById('post-choice');
 const custom=document.getElementById('custom-url');
 function sync(){course.hidden=type.value!=='course';post.hidden=type.value!=='post';custom.hidden=type.value!=='custom';}
 type.addEventListener('change',sync);sync();
})();
</script>
@endsection
