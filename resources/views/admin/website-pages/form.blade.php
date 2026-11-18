@extends('layouts.app')
@section('title', $page->exists ? 'Edit Website Page' : 'Create Website Page')
@section('content')
<div class="page-header"><div><span class="badge info">Admin CMS</span><h1>{{ $page->exists ? 'Edit Page' : 'Create Page' }}</h1><p class="muted">Build a page with reorderable content blocks, custom design, SEO and menu visibility.</p></div><div class="cta-row">@if($page->exists)<a class="btn secondary" href="{{ route('admin.website-pages.revisions', $page) }}">Revision history ({{ $revisionCount ?? 0 }})</a>@endif@if($page->exists)<a class="btn secondary" target="_blank" rel="noopener" href="{{ route('admin.website-pages.preview', $page) }}">Preview</a>@endif<a class="btn secondary" href="{{ route('admin.website-pages.index') }}">Back to Pages</a></div></div>
<form method="POST" action="{{ $page->exists ? route('admin.website-pages.update', $page) : route('admin.website-pages.store') }}">
    <div class="form-group"><label for="academic_level_id">Academic level</label><select id="academic_level_id" name="academic_level_id"><option value="">General website page</option>@foreach($academicLevels as $academicLevel)<option value="{{ $academicLevel->id }}" @selected((string) old('academic_level_id', $page->academic_level_id ?? '') === (string) $academicLevel->id)>{{ $academicLevel->name }}</option>@endforeach</select></div>
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
        <div class="page-header"><div><h2>Page sections</h2><p class="muted">Add up to 30 blocks. Drag each section with its Drag handle, or use the Up and Down buttons. All text is safely escaped; scripts and arbitrary HTML are not allowed.</p></div><button class="btn secondary" type="button" id="add-page-section">+ Add section</button></div>
        <p class="muted" id="page-section-status" role="status" aria-live="polite">Sections can be reordered with a mouse, keyboard-accessible buttons, or touch alternatives using the Up and Down controls.</p>
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
    const status = document.getElementById('page-section-status');
    if (!list || !template || !add) return;
    let draggedCard = null;
    const cards = () => [...list.querySelectorAll('[data-page-section]')];
    const announce = message => { if (status) status.textContent = message; };
    const reindex = () => cards().forEach((card, index) => {
        card.querySelectorAll('[name]').forEach(field => field.name = field.name.replace(/sections\[[^\]]+\]/, 'sections[' + index + ']'));
        const number = card.querySelector('[data-section-number]');
        if (number) number.textContent = String(index + 1);
    });
    add.addEventListener('click', () => {
        const count = cards().length;
        if (count >= 30) {
            announce('Maximum 30 sections per page.');
            return;
        }
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(count)));
        reindex();
        announce('Section ' + (count + 1) + ' added.');
    });
    list.addEventListener('click', event => {
        const move = event.target.closest('[data-move-section]');
        if (move) {
            const card = move.closest('[data-page-section]');
            if (move.dataset.moveSection === 'up' && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
            if (move.dataset.moveSection === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
            reindex();
            announce('Section moved to position ' + (cards().indexOf(card) + 1) + '.');
            return;
        }
        const remove = event.target.closest('[data-remove-page-section]');
        if (!remove) return;
        remove.closest('[data-page-section]')?.remove();
        reindex();
        announce('Section removed.');
    });
    list.addEventListener('dragstart', event => {
        const handle = event.target.closest('[data-drag-handle]');
        const card = event.target.closest('[data-page-section]');
        if (!handle || !card || !event.dataTransfer) {
            event.preventDefault();
            return;
        }
        draggedCard = card;
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'page-section');
    });
    list.addEventListener('dragover', event => {
        if (!draggedCard) return;
        const target = event.target.closest('[data-page-section]');
        if (!target || target === draggedCard) return;
        event.preventDefault();
        const bounds = target.getBoundingClientRect();
        const insertAfter = event.clientY > bounds.top + bounds.height / 2;
        list.insertBefore(draggedCard, insertAfter ? target.nextElementSibling : target);
    });
    list.addEventListener('drop', event => {
        if (!draggedCard) return;
        event.preventDefault();
        reindex();
        announce('Section moved to position ' + (cards().indexOf(draggedCard) + 1) + '.');
    });
    list.addEventListener('dragend', () => {
        draggedCard?.classList.remove('is-dragging');
        draggedCard = null;
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
