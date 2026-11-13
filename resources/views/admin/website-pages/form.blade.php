@extends('layouts.app')
@section('title', $page->exists ? 'Edit Website Page' : 'Create Website Page')
@section('content')
<div class="page-header"><div><span class="badge info">Admin CMS</span><h1>{{ $page->exists ? 'Edit Page' : 'Create Page' }}</h1><p class="muted">Build a page with reorderable content blocks, custom design, SEO and menu visibility.</p></div><a class="btn secondary" href="{{ route('admin.website-pages.index') }}">Back to Pages</a></div>
<form method="POST" action="{{ $page->exists ? route('admin.website-pages.update', $page) : route('admin.website-pages.store') }}">
    @csrf
    @if($page->exists) @method('PUT') @endif
    <section class="panel" style="margin-bottom:20px;">
        <h2>Page settings</h2>
        <div class="grid grid-2">
            <label>Page title<input name="title" maxlength="180" required value="{{ old('title', $page->title) }}"></label>
            <label>URL slug<input name="slug" maxlength="180" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="about-our-center" value="{{ old('slug', $page->slug) }}"><small class="muted">Public URL: /pages/your-slug</small></label>
            <label>Menu label<input name="menu_label" maxlength="80" value="{{ old('menu_label', $page->menu_label) }}" placeholder="About us"></label>
            <label>Template<select name="template">@foreach(['standard'=>'Standard','wide'=>'Wide content','centered'=>'Centered','split'=>'Split layout'] as $value=>$label)<option value="{{ $value }}" @selected(old('template', $page->template ?: 'standard')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="draft" @selected(old('status', $page->status ?: 'draft')==='draft')>Draft</option><option value="published" @selected(old('status', $page->status)==='published')>Published</option></select></label>
            <label>Menu order<input type="number" name="menu_order" min="0" max="10000" value="{{ old('menu_order', $page->menu_order ?? 0) }}"></label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="show_in_menu" value="1" style="width:auto;" @checked(old('show_in_menu', $page->show_in_menu))> Show this page in the public menu</label>
        </div>
    </section>
    <section class="panel" style="margin-bottom:20px;">
        <div class="page-header"><div><h2>Page sections</h2><p class="muted">Add up to 30 blocks. All text is safely escaped; scripts and arbitrary HTML are not allowed.</p></div><button class="btn secondary" type="button" id="add-page-section">+ Add section</button></div>
        <div id="page-section-list">
            @foreach(old('sections', $page->sections ?? []) as $i => $section)
                @include('admin.website-pages.partials.section', ['i'=>$i, 'section'=>$section, 'media'=>$media])
            @endforeach
        </div>
        <template id="page-section-template">@include('admin.website-pages.partials.section', ['i'=>'__INDEX__', 'section'=>[], 'media'=>$media])</template>
    </section>
    <section class="panel" style="margin-bottom:20px;">
        <h2>SEO settings</h2>
        <label>SEO title<input name="seo_title" maxlength="255" value="{{ old('seo_title', $page->seo_title) }}"></label>
        <label>SEO description<textarea name="seo_description" rows="2" maxlength="320">{{ old('seo_description', $page->seo_description) }}</textarea></label>
    </section>
    <div class="cta-row"><button class="btn primary" type="submit">Save Page</button><a class="btn secondary" href="{{ route('admin.website-pages.index') }}">Cancel</a></div>
</form>
@push('scripts')
<script>
(() => {
    const list = document.getElementById('page-section-list');
    const template = document.getElementById('page-section-template');
    const add = document.getElementById('add-page-section');
    const reindex = () => [...list.querySelectorAll('[data-page-section]')].forEach((card, index) => {
        card.querySelectorAll('[name]').forEach(field => field.name = field.name.replace(/sections\[[^\]]+\]/, 'sections[' + index + ']'));
        const number = card.querySelector('[data-section-number]');
        if (number) number.textContent = String(index + 1);
    });
    add?.addEventListener('click', () => {
        const count = list.querySelectorAll('[data-page-section]').length;
        if (count >= 30) return alert('Maximum 30 sections per page.');
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(count)));
        reindex();
    });
    list.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-page-section]');
        if (!remove) return;
        remove.closest('[data-page-section]')?.remove();
        reindex();
    });
    list.addEventListener('change', event => {
        if (!event.target.matches('[data-section-image]')) return;
        const card = event.target.closest('[data-page-section]');
        const preview = card?.querySelector('[data-image-preview]');
        const selected = event.target.selectedOptions[0];
        if (preview) preview.textContent = selected?.textContent || 'No image selected';
    });
})();
</script>
@endpush
@endsection
