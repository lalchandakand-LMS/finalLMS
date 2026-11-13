@extends('layouts.app')
@section('title', $page->seo_title ?: $page->title)
@section('content')
@php
    $pageWidth = $page->template === 'wide' ? 'min(1180px, 100%)' : ($page->template === 'centered' ? 'min(820px, 100%)' : ($page->template === 'split' ? 'min(1080px, 100%)' : 'min(980px, 100%)'));
@endphp
<article class="website-page website-page--{{ $page->template }}" style="max-width:{{ $pageWidth }};margin:24px auto 48px;">
    <header style="padding:clamp(24px,5vw,56px);border-radius:22px;background:linear-gradient(135deg,var(--primary),#0c315e);color:#fff;margin-bottom:22px;">
        <h1 style="color:#fff;margin:0 0 12px;font-size:clamp(30px,5vw,48px);">{{ $page->title }}</h1>
    </header>
    @foreach($page->sections ?? [] as $section)
        @continue(!is_array($section) || empty($section['enabled']))
        @php($asset = !empty($section['image_id']) ? $media->get($section['image_id']) : null)
        <section style="margin:18px 0;padding:clamp(20px,4vw,38px);border-radius:18px;background:{{ $section['background_color'] ?? '#ffffff' }};color:{{ $section['text_color'] ?? '#18212f' }};">
            @if(($section['type'] ?? '') === 'hero')
                <div style="text-align:{{ ($section['layout'] ?? '') === 'centered' ? 'center' : 'left' }};">
                    @if($asset)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->path) }}" alt="{{ $section['title'] ?? $page->title }}" loading="lazy" style="width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:18px;">@endif
                    <h2>{{ $section['title'] }}</h2><p style="line-height:1.8;white-space:pre-line;">{{ $section['body'] }}</p>
                </div>
            @else
                <div style="display:grid;grid-template-columns:{{ $asset ? 'minmax(0,1fr) minmax(0,1fr)' : '1fr' }};gap:24px;align-items:center;">
                    @if($asset && ($section['layout'] ?? '') !== 'reversed')<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->path) }}" alt="{{ $section['title'] ?? $page->title }}" loading="lazy" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px;">@endif
                    <div style="text-align:{{ ($section['layout'] ?? '') === 'centered' ? 'center' : 'left' }};">
                        @if(!empty($section['title']))<h2>{{ $section['title'] }}</h2>@endif
                        @if(!empty($section['body']))<p style="line-height:1.85;white-space:pre-line;">{{ $section['body'] }}</p>@endif
                        @if(!empty($section['button_label']) && !empty($section['button_url']))<a class="btn primary" href="{{ $section['button_url'] }}">{{ $section['button_label'] }}</a>@endif
                    </div>
                    @if($asset && ($section['layout'] ?? '') === 'reversed')<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->path) }}" alt="{{ $section['title'] ?? $page->title }}" loading="lazy" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px;">@endif
                </div>
            @endif
        </section>
    @endforeach
</article>
<style>@media(max-width:680px){.website-page section>div{grid-template-columns:1fr!important}.website-page img{max-height:260px!important}}</style>
@endsection
