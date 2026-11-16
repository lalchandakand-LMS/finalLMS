@extends('layouts.app')
@section('title', __('Website analytics'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Website analytics') }}</h1><p class="muted">{{ __('Unique visitor estimates and content engagement.') }}</p></div><a class="btn secondary" href="{{ route('admin.comments.index') }}">{{ __('Moderate comments') }}</a></div>
<div class="dash-summary">
@foreach([__('Total unique visitors') => $stats['unique_visitors'], __('Visitors today') => $stats['visitors_today'], __('Visitors in 7 days') => $stats['visitors_7_days'], __('Course views') => $stats['course_views'], __('Post views') => $stats['post_views'], __('Pending comments') => $stats['pending_comments'], __('Approved comments') => $stats['approved_comments']] as $label => $value)
<div class="summary-box"><span class="muted">{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>
@endforeach
</div>
<div class="grid grid-2">
<section class="panel"><h2>{{ __('Daily unique visitors') }}</h2>
@forelse($dailyVisitors as $day)<div class="meta-row"><span>{{ $day->viewed_on }}</span><strong>{{ number_format($day->visitors) }}</strong></div>@empty<p class="muted">{{ __('No visitor data yet.') }}</p>@endforelse
</section>
<section class="panel"><h2>{{ __('Top courses') }}</h2>
@forelse($topCourses as $course)<div class="meta-row"><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a><strong>{{ number_format($course->views_count) }} {{ __('views') }}</strong></div>@empty<p class="muted">{{ __('No course views yet.') }}</p>@endforelse
</section>
<section class="panel"><h2>{{ __('Most viewed posts') }}</h2>
@forelse($topPosts as $post)<div class="meta-row"><a href="{{ route('news.show', $post->slug) }}">{{ $post->title }}</a><strong>{{ number_format($post->views_count) }} {{ __('views') }}</strong></div>@empty<p class="muted">{{ __('No post views yet.') }}</p>@endforelse
</section>
</div>
<p class="muted" style="margin-top:16px;">{{ __('Counts are deduplicated per browser session and day; visitors are estimates and may differ from third-party analytics.') }}</p>
@endsection
