@extends('layouts.app')
@section('title', __('Theme Preview').' — '.$theme->name)
@section('content')
<style>
{!! $css !!}
.theme-preview-shell { max-width:1100px;margin:24px auto;padding:28px;border:1px dashed #9aa8bd;border-radius:20px;background:#fff; }
.theme-preview-label { display:inline-block;padding:6px 12px;border-radius:999px;background:#fff1cc;color:#765000;font-weight:700;margin-bottom:18px; }
</style>
<div class="page-header">
    <div><span class="badge warning">{{ __('Unpublished preview') }}</span><h1>{{ $theme->name }}</h1><p class="muted">{{ __('Version') }} {{ $theme->version }} · {{ __('Only administrators can view this preview. The live theme is unchanged.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.themes.index') }}">{{ __('Back to Theme Manager') }}</a>
</div>
<div class="theme-preview-shell">
    <span class="theme-preview-label">{{ __('Theme preview — not live') }}</span>
    <header style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;padding:18px 0;border-bottom:1px solid #e5e7eb;">
        <strong style="font-size:1.5rem;">{{ config('app.name', "Alom's English Center") }}</strong>
        <nav style="display:flex;gap:16px;flex-wrap:wrap;"><span>{{ __('Home') }}</span><span>{{ __('Courses') }}</span><span>{{ __('Teachers') }}</span><span>{{ __('Contact') }}</span></nav>
    </header>
    <section style="padding:48px 8px;text-align:center;">
        <h1 style="font-size:clamp(2rem,5vw,3.5rem);margin:0 0 12px;">{{ __("Learn English with confidence") }}</h1>
        <p style="max-width:650px;margin:0 auto 24px;">{{ __('Explore structured courses, experienced teachers and flexible learning for every student.') }}</p>
        <a class="btn primary" href="#sample-courses">{{ __('Explore Courses') }}</a>
    </section>
    <section id="sample-courses" class="grid grid-3">
        @foreach([__('Spoken English'),__('Grammar & Writing'),__('IELTS Preparation')] as $course)
            <article class="card"><h3>{{ $course }}</h3><p class="muted">{{ __('A guided learning path with lessons, practice and progress tracking.') }}</p></article>
        @endforeach
    </section>
</div>
<div class="panel" style="margin-top:18px;">
    <p class="muted">{{ __('This is a design preview using sample content. Activate the theme from Theme Manager when you are satisfied.') }}</p>
    <a class="btn secondary" href="{{ route('admin.themes.index') }}">{{ __('Return to Theme Manager') }}</a>
</div>
@endsection
