@extends('layouts.app')
@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: $page->title)
@section('content')
@php
    $pageWidth = $page->template === 'wide' ? 'min(1180px, 100%)' : ($page->template === 'centered' ? 'min(820px, 100%)' : ($page->template === 'split' ? 'min(1080px, 100%)' : 'min(980px, 100%)'));
@endphp
@if($isPreview && !$previewFrame)
    <div class="panel" style="margin:18px auto;max-width:1180px;">
        <div class="page-header" style="align-items:center;gap:12px;flex-wrap:wrap;">
            <div><strong>Responsive page preview</strong><p class="muted" style="margin:4px 0 0;">Preview the page at common device widths. This does not change the published page.</p></div>
            <div class="cta-row" role="group" aria-label="Preview device size">
                @foreach(['desktop'=>'Desktop','tablet'=>'Tablet','mobile'=>'Mobile'] as $device=>$label)
                    <a class="btn {{ $previewDevice === $device ? 'primary' : 'secondary' }}" href="{{ route('admin.website-pages.preview', ['websitePage'=>$page->id, 'device'=>$device]) }}" aria-current="{{ $previewDevice === $device ? 'page' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="flash info">Unpublished administrator preview — visitors cannot see this draft.</div>
        <div style="display:flex;justify-content:center;overflow:auto;padding:14px;background:var(--surface-muted,#eef2f7);border-radius:14px;">
            <div style="width:{{ $previewDevice === 'mobile' ? '390px' : ($previewDevice === 'tablet' ? '768px' : '100%') }};max-width:100%;min-width:{{ $previewDevice === 'mobile' ? '320px' : '0' }};border:1px solid #cbd5e1;border-radius:{{ $previewDevice === 'mobile' ? '28px' : '12px' }};padding:{{ $previewDevice === 'mobile' ? '8px' : '0' }};background:#fff;box-shadow:0 8px 28px rgba(15,23,42,.12);">
                <iframe title="{{ ucfirst($previewDevice) }} preview of {{ $page->title }}" src="{{ route('admin.website-pages.preview', ['websitePage'=>$page->id, 'device'=>$previewDevice, 'frame'=>1]) }}" style="display:block;width:100%;height:780px;border:0;border-radius:{{ $previewDevice === 'mobile' ? '20px' : '10px' }};background:#fff;" loading="lazy"></iframe>
            </div>
        </div>
    </div>
@endif
@if($isPreview && $previewFrame)<div class="flash info">Administrator preview</div>@endif
<article class="website-page website-page--{{ $page->template }}" style="max-width:{{ $pageWidth }};margin:24px auto 48px;">
    <header style="padding:clamp(24px,5vw,56px);border-radius:22px;background:linear-gradient(135deg,var(--primary),#0c315e);color:#fff;margin-bottom:22px;">
        <h1 style="color:#fff;margin:0 0 12px;font-size:clamp(30px,5vw,48px);">{{ $page->title }}</h1>
    </header>
    @foreach($page->sections ?? [] as $section)
        @continue(!is_array($section) || empty($section['enabled']))
        @php($asset = !empty($section['image_id']) ? $media->get($section['image_id']) : null)
        <section style="margin:18px 0;padding:clamp(20px,4vw,38px);border-radius:18px;background:{{ $section['background_color'] ?? '#ffffff' }};color:{{ $section['text_color'] ?? '#18212f' }};">
            @if(($section['type'] ?? '') === 'divider')
                <hr style="border:0;border-top:2px solid currentColor;opacity:.25;margin:12px 0;">
            @elseif(($section['type'] ?? '') === 'quote')
                <blockquote style="margin:0;padding:8px 0 8px 22px;border-left:5px solid currentColor;font-size:clamp(20px,3vw,28px);font-weight:600;line-height:1.55;">
                    @if(!empty($section['body']))<p style="white-space:pre-line;margin:0 0 12px;">{{ $section['body'] }}</p>@endif
                    @if(!empty($section['title']))<cite style="display:block;font-size:15px;font-style:normal;font-weight:500;">— {{ $section['title'] }}</cite>@endif
                </blockquote>
            @elseif(($section['type'] ?? '') === 'hero')
                <div style="text-align:{{ ($section['layout'] ?? '') === 'centered' ? 'center' : 'left' }};">
                    @if($asset)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->path) }}" alt="{{ $section['title'] ?? $page->title }}" loading="lazy" style="width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:18px;">@endif
                    <h2>{{ $section['title'] }}</h2><p style="line-height:1.8;white-space:pre-line;">{{ $section['body'] }}</p>
                    @if(!empty($section['button_label']) && !empty($section['button_url']))<a class="btn primary" href="{{ $section['button_url'] }}">{{ $section['button_label'] }}</a>@endif
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
