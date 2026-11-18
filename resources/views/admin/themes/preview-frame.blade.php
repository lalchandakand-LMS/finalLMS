<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $theme->name }} — {{ __('Theme Preview') }}</title>
    <style>
        {!! $css !!}
        :root { color-scheme: light; }
        body { margin: 0; padding: 28px; color: #172033; background: #f5f7fb; font-family: Arial, Helvetica, sans-serif; }
        .theme-preview-shell { max-width: 1100px; margin: 0 auto; padding: 28px; border: 1px dashed #9aa8bd; border-radius: 20px; background: #fff; }
        .theme-preview-label { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #fff1cc; color: #765000; font-weight: 700; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="theme-preview-shell">
    <span class="theme-preview-label">{{ __('Theme preview — not live') }}</span>
    <header style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;padding:18px 0;border-bottom:1px solid #e5e7eb;">
        <strong style="font-size:1.5rem;">{{ config('app.name', "Alom's English Center") }}</strong>
        <nav style="display:flex;gap:16px;flex-wrap:wrap;"><span>{{ __('Home') }}</span><span>{{ __('Courses') }}</span><span>{{ __('Teachers') }}</span><span>{{ __('Contact') }}</span></nav>
    </header>
    <section style="padding:48px 8px;text-align:center;">
        <h1 style="font-size:clamp(2rem,5vw,3.5rem);margin:0 0 12px;">{{ __('Learn English with confidence') }}</h1>
        <p style="max-width:650px;margin:0 auto 24px;">{{ __('Explore structured courses, experienced teachers and flexible learning for every student.') }}</p>
        <a href="#sample-courses">{{ __('Explore Courses') }}</a>
    </section>
    <section id="sample-courses" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;">
        @foreach([__('Spoken English'),__('Grammar & Writing'),__('IELTS Preparation')] as $course)
            <article style="padding:22px;border:1px solid #e5e7eb;border-radius:14px;"><h3>{{ $course }}</h3><p>{{ __('A guided learning path with lessons, practice and progress tracking.') }}</p></article>
        @endforeach
    </section>
</div>
</body>
</html>
