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
@endsection
