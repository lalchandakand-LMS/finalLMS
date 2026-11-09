@extends('layouts.app')
@section('title', 'Homepage Draft Preview')
@section('content')
@php
    $template = $draft['homepage_template'] ?? 'academy-modern';
    $title = $draft['home_title'] ?? 'Your homepage headline';
    $subtitle = $draft['home_subtitle'] ?? '';
    $sections = collect($draft['sections'] ?? [])->filter(fn ($section) => is_array($section) && !empty($section['enabled']));
    $slides = collect($draft['home_slides'] ?? [])->filter(fn ($slide) => is_array($slide) && !empty($slide['enabled']));
    $headerLinks = collect($draft['header_links'] ?? [])->filter(fn ($link) => is_array($link) && !empty($link['label']) && !empty($link['url']));
@endphp
<div class="page-header">
    <div><span class="badge info">Unpublished preview</span><h1>Homepage draft preview</h1><p class="muted">Only administrators can see this preview. It is not visible to visitors until published.</p></div>
    <div class="cta-row"><a class="btn secondary" href="{{ route('admin.website-builder.index') }}">Back to builder</a><form method="POST" action="{{ route('admin.website-builder.publish-draft') }}">@csrf<button class="btn primary" type="submit">Publish this draft</button></form></div>
</div>
<div class="cta-row" style="justify-content:flex-end;margin-bottom:12px;" aria-label="Preview device size">
    <button type="button" class="btn secondary" data-preview-size="desktop" aria-pressed="true">Desktop</button>
    <button type="button" class="btn secondary" data-preview-size="tablet" aria-pressed="false">Tablet</button>
    <button type="button" class="btn secondary" data-preview-size="mobile" aria-pressed="false">Mobile</button>
</div>
<div class="homepage-preview-frame homepage-template homepage-template--{{ $template }}" data-preview-frame>
<div class="panel" style="margin-bottom:18px;">
    <div class="page-header"><div><span class="badge info">{{ str($template)->headline() }}</span><h2>{{ $draft['site_name'] ?? config('app.name') }}</h2></div><span class="muted">Preview mode · not published</span></div>
    <nav class="cta-row" aria-label="Draft navigation">@foreach($headerLinks as $link)<a class="btn secondary" href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</nav>
</div>
<section class="hero" style="margin-bottom:20px;">
    <div><span class="badge info">Homepage hero</span><h1>{{ $title }}</h1><p>{{ $subtitle }}</p><a class="btn primary" href="/courses">{{ $draft['home_cta_primary'] ?? 'Browse courses' }}</a></div>
    <div class="panel"><h2>Learn with confidence</h2><p class="muted">This preview reflects your saved draft content and layout selection.</p></div>
</section>
@if($slides->isNotEmpty())
<section class="panel" style="margin-bottom:20px;"><h2>Homepage slider</h2><div class="grid grid-3">
    @foreach($slides as $slide)
        <article class="card">
            @if(!empty($slide['image_id']) && $media->has($slide['image_id']))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk($media[$slide['image_id']]->disk)->url($media[$slide['image_id']]->path) }}" alt="{{ $slide['title'] ?? 'Homepage slide' }}" style="width:100%;max-height:180px;object-fit:cover;border-radius:12px;">
            @endif
            <h3>{{ $slide['title'] ?? '' }}</h3><p>{{ $slide['subtitle'] ?? '' }}</p>
        </article>
    @endforeach
</div></section>
@endif
@if($sections->isNotEmpty())
<section class="panel"><h2>Custom homepage sections</h2>
    @foreach($sections as $section)
        <article class="card" style="margin:14px 0;">
            @if(!empty($section['image_id']) && $media->has($section['image_id']))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk($media[$section['image_id']]->disk)->url($media[$section['image_id']]->path) }}" alt="{{ $section['title'] ?? 'Section image' }}" style="width:100%;max-width:420px;max-height:220px;object-fit:cover;border-radius:12px;">
            @endif
            <h3>{{ $section['title'] ?? '' }}</h3><p>{{ $section['body'] ?? '' }}</p>
            @if(!empty($section['button_label']))<a class="btn secondary" href="{{ $section['button_url'] ?? '#' }}">{{ $section['button_label'] }}</a>@endif
        </article>
    @endforeach
</section>
@endif
</div>
<style>
    .homepage-preview-frame { width:100%; margin:0 auto; transition:max-width .2s ease; --preview-accent:#2457c5; --preview-radius:20px; }
    .homepage-preview-frame .hero { border-radius:var(--preview-radius); padding:clamp(18px,3vw,32px); }
    .homepage-preview-frame .panel,.homepage-preview-frame .card { border-radius:var(--preview-radius); }
    .homepage-preview-frame .btn.primary { background:var(--preview-accent); }
    .homepage-template--academy-modern { --preview-accent:#2457c5; --preview-radius:20px; }
    .homepage-template--academy-modern .hero { background:linear-gradient(135deg,#eff6ff,#fff 60%); }
    .homepage-template--bold-academy { --preview-accent:#172554; --preview-radius:10px; }
    .homepage-template--bold-academy .hero { background:#172554; color:#fff; }
    .homepage-template--bold-academy .hero h1,.homepage-template--bold-academy .hero p { color:#fff; }
    .homepage-template--minimal-learning { --preview-accent:#0f766e; --preview-radius:8px; }
    .homepage-template--minimal-learning .hero { background:#f8fafc; border-left:5px solid var(--preview-accent); }
    .homepage-template--editorial { --preview-accent:#92400e; --preview-radius:4px; }
    .homepage-template--editorial .hero h1 { font-family:Georgia,serif; }
    .homepage-template--gradient-studio { --preview-accent:#7c3aed; --preview-radius:26px; }
    .homepage-template--gradient-studio .hero { background:linear-gradient(125deg,#ede9fe,#dbeafe 55%,#fce7f3); }
    .homepage-template--corporate-training { --preview-accent:#075985; --preview-radius:6px; }
    .homepage-template--corporate-training .hero { background:#eaf2f8; border-bottom:5px solid var(--preview-accent); }
    .homepage-template--playful-classroom { --preview-accent:#db2777; --preview-radius:28px; }
    .homepage-template--playful-classroom .hero { background:#fff1f2; border-radius:36px 12px; }
    .homepage-template--midnight-campus { --preview-accent:#38bdf8; --preview-radius:16px; background:#0f172a; color:#e2e8f0; padding:18px; border-radius:24px; }
    .homepage-template--midnight-campus .hero,.homepage-template--midnight-campus .panel,.homepage-template--midnight-campus .card { background:#1e293b; color:#e2e8f0; border-color:#334155; }
    .homepage-template--midnight-campus h1,.homepage-template--midnight-campus h2,.homepage-template--midnight-campus h3 { color:#f8fafc; }
    .homepage-template--split-screen { --preview-accent:#4338ca; --preview-radius:14px; }
    .homepage-template--split-screen .hero { background:#eef2ff; }
    .homepage-template--classic-institute { --preview-accent:#166534; --preview-radius:2px; }
    .homepage-template--classic-institute .hero { border-block:4px double var(--preview-accent); }
    .homepage-template--classic-institute .hero h1 { font-family:Georgia,serif; }
    .homepage-template--glass-campus { --preview-accent:#0e7490; --preview-radius:22px; }
    .homepage-template--glass-campus .hero { background:linear-gradient(135deg,#cffafe,#e0f2fe); border:1px solid #bae6fd; }
    .homepage-template--vibrant-skills { --preview-accent:#ea580c; --preview-radius:18px; }
    .homepage-template--vibrant-skills .hero { background:linear-gradient(120deg,#ffedd5,#fef3c7); }
    .homepage-template--focus-learning { --preview-accent:#4338ca; --preview-radius:12px; }
    .homepage-template--focus-learning .hero { grid-template-columns:1fr; text-align:center; }
    .homepage-template--elegant-academy { --preview-accent:#7c2d12; --preview-radius:10px; }
    .homepage-template--elegant-academy .hero { background:#fffbeb; border:1px solid #e7d9c4; }
    .homepage-template--community-campus { --preview-accent:#047857; --preview-radius:24px; }
    .homepage-template--community-campus .hero { background:#ecfdf5; border-radius:28px 28px 8px 28px; }
    .homepage-preview-frame[data-size="tablet"] { max-width:820px; }
    .homepage-preview-frame[data-size="mobile"] { max-width:390px; border:8px solid #1f2937; border-radius:28px; padding:10px; background:#f8fafc; box-shadow:0 20px 50px #0f172a24; }
    @media(max-width:600px) { .homepage-preview-frame[data-size="mobile"] { max-width:100%; border-width:4px; border-radius:18px; } }
</style>
@push('scripts')
<script>
(() => {
    const frame = document.querySelector('[data-preview-frame]');
    const buttons = document.querySelectorAll('[data-preview-size]');
    if (!frame || !buttons.length) return;
    buttons.forEach((button) => button.addEventListener('click', () => {
        frame.dataset.size = button.dataset.previewSize;
        buttons.forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
    }));
})();
</script>
@endpush
@endsection
