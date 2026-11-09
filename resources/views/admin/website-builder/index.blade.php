@extends('layouts.app')
@section('title', __('Website Builder'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin CMS') }}</span><h1>{{ __('Website Builder') }}</h1><p class="muted">Control your public website identity, homepage slider and content sections from one place.</p></div>
    <div class="cta-row"><a href="{{ route('admin.media.index') }}" class="btn secondary">{{ __('Media Library') }}</a><a href="{{ route('landing') }}" class="btn secondary" target="_blank" rel="noopener">{{ __('Live website') }}</a>@if($draft)<a href="{{ route('admin.website-builder.preview') }}" class="btn primary" target="_blank" rel="noopener">Preview saved draft</a>@endif</div>
</div>
<form method="POST" action="{{ route('admin.website-builder.update') }}">
@csrf
<section class="panel" style="margin-bottom:20px;">
    <div class="page-header"><div><h2>Choose a homepage design</h2><p class="muted">Select one of 15 responsive visual presets. Your courses, users, media, homepage copy and saved sections remain unchanged when you switch designs.</p></div><span class="badge info">15 designs</span></div>
    <div class="grid grid-3">
        @foreach($templates as $templateKey => $template)
            <label class="card" style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;border:2px solid {{ old('homepage_template', $activeTemplate) === $templateKey ? 'var(--primary)' : 'var(--border)' }};">
                <input type="radio" name="homepage_template" value="{{ $templateKey }}" style="width:auto;margin-top:4px;" @checked(old('homepage_template', $activeTemplate) === $templateKey)>
                <span><strong>{{ $template['label'] }}</strong><span class="muted" style="display:block;font-size:13px;margin-top:5px;">{{ $template['description'] }}</span></span>
            </label>
        @endforeach
    </div>
</section>
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
    <div class="card" style="margin-bottom:16px;">
        <h3>Reusable section block library</h3><p class="muted">Insert a ready-made content structure into the first empty section, then edit it before publishing.</p>
        <div class="cta-row">
            <button type="button" class="btn secondary" data-section-preset data-title="Why learn with us" data-body="Expert teachers, practical lessons and guided support help every learner make measurable progress.">Why learn with us</button>
            <button type="button" class="btn secondary" data-section-preset data-title="Start learning today" data-body="Explore our courses, choose your learning path and take the next step with Alom’s English Center." data-button-label="Browse courses" data-button-url="/courses">Call to action</button>
            <button type="button" class="btn secondary" data-section-preset data-title="Learning outcomes" data-body="Build confidence, strengthen communication skills and apply what you learn in real situations.">Learning outcomes</button>
            <button type="button" class="btn secondary" data-section-preset data-title="Frequently asked questions" data-body="Find answers about course access, class schedules, payments and student support.">FAQ introduction</button>
        </div>
    </div>
    <div data-section-list>
    @for($i = 0; $i < 12; $i++)
        @php($section = old("sections.$i", $sections[$i] ?? []))
        <section class="card website-section-item" draggable="true" style="margin-bottom:16px;" data-section-item>
            <div class="page-header"><h3><span class="section-position">{{ $i + 1 }}</span> · Section</h3><button type="button" class="btn secondary section-drag-handle" aria-label="Drag to reorder section" style="cursor:grab;">↕ Drag to reorder</button><label style="display:flex;gap:8px;align-items:center;"><input type="hidden" name="sections[{{ $i }}][enabled]" value="0"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" style="width:auto;" @checked($section['enabled'] ?? false)> Enabled</label></div>
            <label>Heading<input name="sections[{{ $i }}][title]" maxlength="180" value="{{ $section['title'] ?? '' }}"></label>
            <label>Text<textarea name="sections[{{ $i }}][body]" rows="3" maxlength="3000">{{ $section['body'] ?? '' }}</textarea></label>
            <label>Image<select name="sections[{{ $i }}][image_id]"><option value="">No image</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected(($section['image_id'] ?? '') == $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
            <div class="grid grid-2"><div><label>Button label<input name="sections[{{ $i }}][button_label]" maxlength="60" value="{{ $section['button_label'] ?? '' }}"></label></div><div><label>Button URL<input name="sections[{{ $i }}][button_url]" type="text" maxlength="500" value="{{ $section['button_url'] ?? '' }}" placeholder="/courses"></label></div></div>
            <div class="grid grid-2">
                <div><label>Section background color<input type="color" name="sections[{{ $i }}][background_color]" value="{{ $section['background_color'] ?? '#ffffff' }}"></label></div>
                <div><label>Text color<input type="color" name="sections[{{ $i }}][text_color]" value="{{ $section['text_color'] ?? '#18212f' }}"></label></div>
                <div><label>Content spacing<select name="sections[{{ $i }}][spacing]"><option value="compact" @selected(($section['spacing'] ?? 'normal') === 'compact')>Compact</option><option value="normal" @selected(($section['spacing'] ?? 'normal') === 'normal')>Normal</option><option value="airy" @selected(($section['spacing'] ?? 'normal') === 'airy')>Airy</option></select></label></div>
                <div><label>Content layout<select name="sections[{{ $i }}][layout]"><option value="standard" @selected(($section['layout'] ?? 'standard') === 'standard')>Text left, image right</option><option value="reversed" @selected(($section['layout'] ?? 'standard') === 'reversed')>Image left, text right</option><option value="centered" @selected(($section['layout'] ?? 'standard') === 'centered')>Centered content</option></select></label></div>
            </div>
        </section>
    @endfor
    </div>
</section>
<section class="panel" style="margin-bottom:20px;">
    <h2>Header navigation menu</h2>
    <p class="muted">Add up to six custom links. When saved, these replace the default public links (Home, Courses, About, Contact, FAQ and Blog) while account controls remain available.</p>
    @for($i = 0; $i < 6; $i++)
        @php($menuLink = old("header_links.$i", $headerLinks[$i] ?? []))
        <div class="grid grid-2">
            <div><label>Menu item {{ $i + 1 }} label<input name="header_links[{{ $i }}][label]" maxlength="60" value="{{ $menuLink['label'] ?? '' }}"></label></div>
            <div><label>Menu item {{ $i + 1 }} URL<input name="header_links[{{ $i }}][url]" maxlength="500" value="{{ $menuLink['url'] ?? '' }}" placeholder="/courses"></label></div>
        </div>
    @endfor
</section>
<section class="panel" style="margin-bottom:20px;">
    <h2>Footer content & links</h2>
    <div class="grid grid-2">
        <div><label for="footer-title">Footer heading</label><input id="footer-title" name="footer_title" maxlength="120" value="{{ old('footer_title', $settings['footer_title'] ?? $settings['site_name'] ?? config('app.name')) }}"></div>
        <div><label for="footer-text">Footer description</label><textarea id="footer-text" name="footer_text" rows="2" maxlength="500">{{ old('footer_text', $settings['footer_text'] ?? '') }}</textarea></div>
    </div>
    <p class="muted">Add up to five footer links. Use internal paths such as /courses or full https:// URLs.</p>
    @for($i = 0; $i < 5; $i++)
        @php($link = old("footer_links.$i", $footerLinks[$i] ?? []))
        <div class="grid grid-2">
            <div><label>Link {{ $i + 1 }} label<input name="footer_links[{{ $i }}][label]" maxlength="60" value="{{ $link['label'] ?? '' }}"></label></div>
            <div><label>Link {{ $i + 1 }} URL<input name="footer_links[{{ $i }}][url]" maxlength="500" value="{{ $link['url'] ?? '' }}" placeholder="/courses"></label></div>
        </div>
    @endfor
</section>
<div class="cta-row"><button type="submit" class="btn secondary" formaction="{{ route('admin.website-builder.draft') }}">Save as draft</button><button type="submit" class="btn primary">Publish changes now</button></div>
</form>
@if($draft)
<section class="panel" style="margin-top:20px;">
    <div class="page-header"><div><span class="badge info">Unpublished draft</span><h2>Draft ready for review</h2><p class="muted">Saving a draft does not change the public website. Preview it, then publish when approved.</p></div><form method="POST" action="{{ route('admin.website-builder.publish-draft') }}">@csrf<button class="btn primary" type="submit">Publish saved draft</button></form></div>
    <p class="muted">Last saved: {{ $settings['homepage_draft_updated_at'] ?? 'Recently' }}</p>
</section>
@endif
@if(count($revisions))
<section class="panel" style="margin-top:20px;">
    <h2>Homepage revision history</h2><p class="muted">Up to 20 previous published versions are retained for rollback.</p>
    @foreach(array_reverse($revisions) as $revision)
        <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:14px;margin:10px 0;flex-wrap:wrap;">
            <div><strong>{{ $revision['label'] ?? 'Published homepage' }}</strong><div class="muted">{{ $revision['created_at'] ?? '' }}</div></div>
            <form method="POST" action="{{ route('admin.website-builder.rollback') }}" onsubmit="return confirm('Restore this homepage revision? The current version will be retained in history.');">@csrf<input type="hidden" name="revision_id" value="{{ $revision['id'] ?? '' }}"><button type="submit" class="btn secondary">Restore this version</button></form>
        </div>
    @endforeach
</section>
@endif
@push('scripts')
<script>
(() => {
    const list = document.querySelector('[data-section-list]');
    if (!list) return;
    let dragged = null;
    const refresh = () => {
        [...list.querySelectorAll('[data-section-item]')].forEach((card, index) => {
            card.querySelector('.section-position').textContent = String(index + 1);
            card.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/sections\[\d+\]/, 'sections[' + index + ']');
            });
        });
    };
    document.querySelectorAll('[data-section-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const cards = [...list.querySelectorAll('[data-section-item]')];
            const target = cards.find((card) => {
                const title = card.querySelector('[name$="[title]"]');
                return title && !title.value.trim();
            });
            if (!target) {
                window.alert('All 12 homepage sections are in use. Clear a section before inserting another block.');
                return;
            }
            const setField = (suffix, value) => {
                const field = target.querySelector('[name$="[' + suffix + ']"]');
                if (field) field.value = value || '';
            };
            setField('title', button.dataset.title);
            setField('body', button.dataset.body);
            setField('button_label', button.dataset.buttonLabel);
            setField('button_url', button.dataset.buttonUrl);
            const enabled = target.querySelector('input[type="checkbox"][name$="[enabled]"]');
            if (enabled) enabled.checked = true;
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
    list.querySelectorAll('[data-section-item]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            dragged = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!dragged || dragged === card) return;
            const bounds = card.getBoundingClientRect();
            const after = event.clientY > bounds.top + bounds.height / 2;
            list.insertBefore(dragged, after ? card.nextSibling : card);
        });
        card.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
            refresh();
        });
    });
})();
</script>
@endpush
@endsection
