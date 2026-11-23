@extends('layouts.app')
@section('title', __('Our Instructors').' | '.config('app.name'))
@section('seo_title', __('Meet our instructors'))
@section('seo_description', __('Meet the teachers behind our published courses and explore the subjects they teach.'))
@section('content')
<style>
.instructor-hero{padding:clamp(24px,4vw,42px);margin-bottom:24px;border-radius:22px;color:#fff;background:linear-gradient(120deg,#102c52,#176b85 68%,#1c8a8a)}
.instructor-hero h1{margin:8px 0;color:#fff;font-size:clamp(1.8rem,4vw,2.7rem)}
.instructor-hero p{max-width:680px;margin:0;color:#d7edf4;line-height:1.7}
.instructor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.instructor-card{display:flex;min-width:0;flex-direction:column;padding:20px;border:1px solid var(--border);border-radius:17px;background:#fff;box-shadow:0 8px 24px rgba(16,42,76,.06)}
.instructor-avatar{display:grid;width:64px;height:64px;place-items:center;margin-bottom:14px;border-radius:18px;background:#e8f2ff;color:#1546a8;font-size:1.5rem;font-weight:900}
.instructor-card h2{margin:0 0 5px;font-size:1.1rem}
.instructor-course-list{display:grid;gap:8px;margin:14px 0 18px;padding:0;list-style:none}
.instructor-course-list a{display:block;padding:9px 11px;border-radius:9px;background:#f3f7fc;font-weight:700;font-size:.86rem}
.instructor-card .btn{margin-top:auto;align-self:flex-start}
@media(max-width:850px){.instructor-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.instructor-grid{grid-template-columns:1fr}}
</style>
<section class="instructor-hero">
<span style="font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ce7d8;">{{ __('Learn from experienced educators') }}</span>
<h1>{{ __('Meet our instructors') }}</h1>
<p>{{ __('Explore the teachers who lead our published courses. Find a subject, discover a course and take your next step with Alom’s English Center.') }}</p>
</section>
<form method="GET" action="{{ route('instructors.index') }}" class="panel" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:22px;">
<label style="flex:1;min-width:220px;">{{ __('Search instructors or courses') }}
<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ __('Teacher name or course title') }}">
</label>
<button class="btn primary" type="submit">{{ __('Search') }}</button>
<a class="btn secondary" href="{{ route('instructors.index') }}">{{ __('Clear') }}</a>
<span class="muted">{{ number_format($teachers->total()) }} {{ __('instructors') }}</span>
</form>
@if($teachers->isNotEmpty())
<div class="instructor-grid">
@foreach($teachers as $teacher)
<article class="instructor-card">
<div class="instructor-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($teacher->name, 0, 1)) }}</div>
<h2>{{ $teacher->name }}</h2>
<p class="muted" style="margin:0;">{{ number_format($teacher->published_courses_count) }} {{ __('published courses') }}</p>
<ul class="instructor-course-list">
@foreach($teacher->courses->take(4) as $course)
<li><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></li>
@endforeach
</ul>
<a class="btn secondary" href="{{ route('courses.index', ['teacher' => $teacher->id]) }}">{{ __('View instructor courses') }}</a>
</article>
@endforeach
</div>
<div style="margin-top:24px;">{{ $teachers->links() }}</div>
@else
<section class="panel" style="text-align:center;padding:36px;">
<h2>{{ __('No instructors found') }}</h2>
<p class="muted">{{ __('Try another name or course title. Instructors appear here when they have at least one published course.') }}</p>
<a class="btn primary" href="{{ route('courses.index') }}">{{ __('Browse courses') }}</a>
</section>
@endif
@endsection
