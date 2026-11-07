@extends('layouts.app')
@section('title', $lesson->title . ' | ' . $course->title)
@section('content')
<div class="page-header"><div><a href="{{ route('courses.show', $course) }}" class="muted">← {{ __('Back to course') }}</a><h1 style="margin:12px 0 0;">{{ $lesson->title }}</h1><p class="muted">{{ $course->title }} · {{ $lesson->duration_minutes }} {{ __('minutes') }}</p></div>@if($isCompleted)<span class="badge success">{{ __('Completed') }}</span>@endif</div>
<div class="panel">
@if($videoEmbedUrl)<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;background:#0f172a;"><iframe src="{{ $videoEmbedUrl }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe></div>@elseif($lesson->video_url)<p class="muted">{{ __('This video provider is not supported for embedded playback.') }}</p>@endif
<div style="margin-top:24px;line-height:1.8;">{!! nl2br(e($lesson->content)) !!}</div>
@auth @if(auth()->user()->isStudent() && ! $isCompleted && ! $lesson->is_preview)<form method="POST" action="{{ route('courses.lessons.complete', [$course, $lesson]) }}" style="margin-top:24px;">@csrf<button type="submit" class="btn primary">{{ __('Mark lesson complete') }}</button></form>@endif @endauth
</div>
@endsection
