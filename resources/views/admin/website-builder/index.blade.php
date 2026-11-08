@extends('layouts.app')
@section('title', __('Website Builder'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin CMS') }}</span><h1>{{ __('Website Builder') }}</h1><p class="muted">Control your public website identity, homepage slider and content sections from one place.</p></div>
    <div class="cta-row"><a href="{{ route('admin.media.index') }}" class="btn secondary">{{ __('Media Library') }}</a><a href="{{ route('landing') }}" class="btn primary" target="_blank" rel="noopener">{{ __('Preview website') }}</a></div>
</div>
<form method="POST" action="{{ route('admin.website-builder.update') }}">
@csrf
<section class="panel" style="margin-bottom:20px;">
    <h2>Website identity & branding</h2>
    <p class="muted">Upload your logo and favicon in Media Library first, then select them below.</p>
    <div class="grid grid-2">
        <div><label for="site-name">Website name</label><input id="site-name" name="site_name" maxlength="120" value="{{ old('site_name', $settings['site_name'] ?? config('app.name')) }}"></div>
        <div><label for="site-primary-color">Primary brand color</label><input id="site-primary-color" name="site_primary_color" type="text" pattern="#[0-9A-Fa-f]{6}" placeholder="#1546A8" value="{{ old('site_primary_color', $settings['site_primary_color'] ?? '#1546A8') }}"></div>
        <div><label for="site-logo">Website logo</label><select id="site-logo" name="site_logo_media_id"><option value="">Use text name</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected((string) old('site_logo_media_id', $settings['site_logo_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></div>
        <div><label for="site-favicon">Favicon</label><select id="site-favicon" name="site_favicon_media_id"><option value="">Use default icon</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected((string) old('site_favicon_media_id', $settings['site_favicon_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></div>
    </div>
</section>
<section class="panel" style="margin-bottom:20px;">
    <h2>Homepage hero content</h2>
    <div class="grid grid-2">
        <div><label for="home-title">Main heading</label><input id="home-title" name="home_title" maxlength="255" value="{{ old('home_title', $settings['home_title'] ?? '') }}"></div>
        <div><label for="home-featured-title">Featured courses heading</label><input id="home-featured-title" name="home_featured_title" maxlength="255" value="{{ old('home_featured_title', $settings['home_featured_title'] ?? '') }}"></div>
    </div>
    <label for="home-subtitle">Subtitle</label><textarea id="home-subtitle" name="home_subtitle" rows="3" maxlength="500">{{ old('home_subtitle', $settings['home_subtitle'] ?? '') }}</textarea>
</section>
<section class="panel" style="margin-bottom:20px;">
    <h2>Homepage slider</h2>
    <p class="muted">Add up to three image slides. Upload images in Media Library before selecting them.</p>
    @for($i = 0; $i < 3; $i++)
        @php($slide = old("home_slides.$i", $slides[$i] ?? []))
        <section class="card" style="margin-bottom:14px;">
            <div class="page-header"><h3>Slide {{ $i + 1 }}</h3><label style="display:flex;gap:8px;align-items:center;"><input type="hidden" name="home_slides[{{ $i }}][enabled]" value="0"><input type="checkbox" name="home_slides[{{ $i }}][enabled]" value="1" style="width:auto;" @checked($slide['enabled'] ?? false)> Enabled</label></div>
            <label>Image<select name="home_slides[{{ $i }}][image_id]"><option value="">Select image</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected((string) ($slide['image_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
            <div class="grid grid-2">
                <div><label>Slide title<input name="home_slides[{{ $i }}][title]" maxlength="180" value="{{ $slide['title'] ?? '' }}"></label></div>
                <div><label>Slide subtitle<textarea name="home_slides[{{ $i }}][subtitle]" rows="2" maxlength="500">{{ $slide['subtitle'] ?? '' }}</textarea></label></div>
                <div><label>Button label<input name="home_slides[{{ $i }}][button_label]" maxlength="60" value="{{ $slide['button_label'] ?? '' }}"></label></div>
                <div><label>Button URL<input name="home_slides[{{ $i }}][button_url]" maxlength="500" value="{{ $slide['button_url'] ?? '' }}" placeholder="/courses"></label></div>
            </div>
        </section>
    @endfor
</section>
<section class="panel" style="margin-bottom:20px;">
    <div class="page-header"><div><h2>Homepage content sections</h2><p class="muted">Enable and fill up to 12 custom sections. HTML and scripts are not accepted.</p></div></div>
    @for($i = 0; $i < 12; $i++)
        @php($section = old("sections.$i", $sections[$i] ?? []))
        <section class="card" style="margin-bottom:16px;">
            <div class="page-header"><h3>Section {{ $i + 1 }}</h3><label style="display:flex;gap:8px;align-items:center;"><input type="hidden" name="sections[{{ $i }}][enabled]" value="0"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" style="width:auto;" @checked($section['enabled'] ?? false)> Enabled</label></div>
            <label>Heading<input name="sections[{{ $i }}][title]" maxlength="180" value="{{ $section['title'] ?? '' }}"></label>
            <label>Text<textarea name="sections[{{ $i }}][body]" rows="3" maxlength="3000">{{ $section['body'] ?? '' }}</textarea></label>
            <label>Image<select name="sections[{{ $i }}][image_id]"><option value="">No image</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected(($section['image_id'] ?? '') == $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
            <div class="grid grid-2"><div><label>Button label<input name="sections[{{ $i }}][button_label]" maxlength="60" value="{{ $section['button_label'] ?? '' }}"></label></div><div><label>Button URL<input name="sections[{{ $i }}][button_url]" type="url" maxlength="500" value="{{ $section['button_url'] ?? '' }}"></label></div></div>
        </section>
    @endfor
</section>
<button type="submit" class="btn primary">Save website design</button>
</form>
@endsection
