@extends('layouts.app')

@section('title', __('Main Menu Builder') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Website settings') }}</span>
        <h1>{{ __('Main Menu Builder') }}</h1>
        <p class="muted">{{ __('Add, edit, hide, delete and organize public navigation links and one-level submenus.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.website-builder.index') }}">{{ __('Website builder') }}</a>
</div>

<div class="grid grid-2" style="align-items:start;gap:18px;">
    <section class="panel">
        <h2>{{ __('Add menu item') }}</h2>
        <form method="POST" action="{{ route('admin.website-menu.store') }}" class="form-grid">
            @csrf
            <label>{{ __('Menu label') }}<input name="label" maxlength="80" required value="{{ old('label') }}"></label>
            <label>{{ __('Link URL') }}<input name="url" maxlength="500" required placeholder="/about or https://example.com" value="{{ old('url') }}"><small class="muted">{{ __('Only site-relative paths and HTTPS links are allowed.') }}</small></label>
            <label>{{ __('Parent item (optional)') }}<select name="parent_id"><option value="">{{ __('Top-level item') }}</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->label }}</option>@endforeach</select></label>
            <label>{{ __('Display order') }}<input type="number" name="sort_order" min="0" max="100000" value="0"></label>
            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> {{ __('Visible on website') }}</label>
            <label class="checkbox-row"><input type="checkbox" name="target_blank" value="1"> {{ __('Open in new tab') }}</label>
            <button class="btn primary" type="submit">{{ __('Add menu item') }}</button>
        </form>
    </section>

    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <div><h2>{{ __('Arrange menu') }}</h2><p class="muted">{{ __('Drag rows to change their order. Use the edit form to change labels, links or submenu parent.') }}</p></div>
            <span class="badge info">{{ $items->count() }} {{ __('items') }}</span>
        </div>
        <form method="POST" action="{{ route('admin.website-menu.reorder') }}" id="menu-order-form">
            @csrf
            <div id="menu-sort-list" style="display:grid;gap:8px;">
                @forelse($items->whereNull('parent_id') as $item)
                    <div class="menu-sort-row card" draggable="true" data-id="{{ $item->id }}" style="padding:12px;cursor:grab;">
                        <strong>☷ {{ $item->label }}</strong>
                        <small class="muted">{{ $item->url }} · {{ $item->is_active ? __('Visible') : __('Hidden') }}</small>
                        @foreach($items->where('parent_id', $item->id) as $child)
                            <div class="muted" style="padding-left:22px;">↳ {{ $child->label }} — {{ $child->url }}</div>
                        @endforeach
                    </div>
                @empty
                    <p class="muted">{{ __('No menu items created yet.') }}</p>
                @endforelse
            </div>
            <div id="menu-order-inputs"></div>
            @if($items->whereNull('parent_id')->isNotEmpty())
                <button class="btn primary" type="submit" style="margin-top:14px;">{{ __('Save drag order') }}</button>
            @endif
        </form>
    </section>
</div>

<section class="panel" style="margin-top:18px;">
    <h2>{{ __('Manage menu links') }}</h2>
    @forelse($items as $item)
        <form method="POST" action="{{ route('admin.website-menu.update', $item) }}" class="card form-grid" style="margin:12px 0;padding:16px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end;">
            @csrf @method('PUT')
            <label>{{ __('Label') }}<input name="label" maxlength="80" required value="{{ $item->label }}"></label>
            <label>{{ __('URL') }}<input name="url" maxlength="500" required value="{{ $item->url }}"></label>
            <label>{{ __('Parent') }}<select name="parent_id"><option value="">{{ __('Top-level') }}</option>@foreach($parents->where('id', '!=', $item->id) as $parent)<option value="{{ $parent->id }}" @selected($item->parent_id === $parent->id)>{{ $parent->label }}</option>@endforeach</select></label>
            <label>{{ __('Order') }}<input type="number" name="sort_order" min="0" max="100000" value="{{ $item->sort_order }}"></label>
            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> {{ __('Visible') }}</label>
            <label class="checkbox-row"><input type="checkbox" name="target_blank" value="1" @checked($item->target_blank)> {{ __('New tab') }}</label>
            <button class="btn primary" type="submit">{{ __('Save changes') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.website-menu.destroy', $item) }}" onsubmit="return confirm('{{ __('Delete this menu item? Child links will become top-level links.') }}')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">{{ __('Delete') }}: {{ $item->label }}</button>
        </form>
    @empty
        <p class="muted">{{ __('No menu links to manage.') }}</p>
    @endforelse
</section>

<script>
(() => {
    const list = document.getElementById('menu-sort-list');
    const form = document.getElementById('menu-order-form');
    const inputs = document.getElementById('menu-order-inputs');
    if (!list || !form || !inputs) return;
    let dragged = null;
    list.querySelectorAll('[draggable="true"]').forEach((row) => {
        row.addEventListener('dragstart', () => { dragged = row; row.style.opacity = '0.5'; });
        row.addEventListener('dragend', () => { if (dragged) dragged.style.opacity = ''; dragged = null; });
        row.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!dragged || dragged === row) return;
            const bounds = row.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? row : row.nextSibling);
        });
    });
    form.addEventListener('submit', () => {
        inputs.replaceChildren();
        list.querySelectorAll('[data-id]').forEach((row) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[]';
            input.value = row.dataset.id;
            inputs.appendChild(input);
        });
    });
})();
</script>
@endsection
