@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6">
    <div><a href="{{ route('admin.carousels.index') }}" class="text-sm text-blue-700">← All carousels</a><h1 class="mt-2 text-2xl font-bold">{{ $carousel->exists ? 'Edit carousel' : 'Create carousel' }}</h1><p class="text-sm text-gray-600">Configure content, responsive cards, motion, navigation and publication schedule.</p></div>
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $carousel->exists ? route('admin.carousels.update',$carousel->id) : route('admin.carousels.store') }}" class="space-y-6 rounded-xl border bg-white p-5">
        @csrf @if($carousel->exists) @method('PUT') @endif
        <section class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Carousel title<input required maxlength="160" name="title" value="{{ old('title',$carousel->title) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="block text-sm font-semibold">Unique slug<input required maxlength="180" name="slug" value="{{ old('slug',$carousel->slug) }}" pattern="[A-Za-z0-9_-]+" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="block text-sm font-semibold">Content source<select name="source_type" id="carousel-source" class="mt-1 w-full rounded-lg border p-2">@foreach(['manual'=>'Manual items','courses'=>'Published courses','teachers'=>'Teacher profiles','testimonials'=>'Approved testimonials'] as $key=>$label)<option value="{{ $key }}" @selected(old('source_type',$carousel->source_type)===$key)>{{ $label }}</option>@endforeach</select></label>
            <label class="block text-sm font-semibold">Publication status<select name="status" class="mt-1 w-full rounded-lg border p-2"><option value="draft" @selected(old('status',$carousel->status)==='draft')>Draft</option><option value="published" @selected(old('status',$carousel->status)==='published')>Published</option></select></label>
        </section>
        <section class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-semibold">Maximum items<input type="number" min="1" max="30" name="item_limit" value="{{ old('item_limit',$carousel->item_limit) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="text-sm font-semibold">Desktop cards visible<input type="number" min="1" max="6" name="desktop_cards" value="{{ old('desktop_cards',$carousel->desktop_cards) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="text-sm font-semibold">Mobile cards visible<select name="mobile_cards" class="mt-1 w-full rounded-lg border p-2"><option value="1" @selected(old('mobile_cards',$carousel->mobile_cards)==1)>1</option><option value="2" @selected(old('mobile_cards',$carousel->mobile_cards)==2)>2</option></select></label>
            <label class="text-sm font-semibold">Display order<input type="number" min="0" max="100000" name="sort_order" value="{{ old('sort_order',$carousel->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="text-sm font-semibold">Autoplay interval (ms)<input type="number" min="2500" max="15000" step="500" name="interval_ms" value="{{ old('interval_ms',$carousel->interval_ms) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <div class="flex flex-wrap items-center gap-4 pt-6 text-sm"><label><input type="checkbox" name="autoplay" value="1" @checked(old('autoplay',$carousel->autoplay))> Autoplay</label><label><input type="checkbox" name="show_arrows" value="1" @checked(old('show_arrows',$carousel->show_arrows))> Arrows</label><label><input type="checkbox" name="show_dots" value="1" @checked(old('show_dots',$carousel->show_dots))> Dots</label></div>
            <label class="text-sm font-semibold">Publish from<input type="datetime-local" name="starts_at" value="{{ old('starts_at',$carousel->starts_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            <label class="text-sm font-semibold">Publish until<input type="datetime-local" name="ends_at" value="{{ old('ends_at',$carousel->ends_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border p-2"></label>
        </section>
        <section id="manual-items" class="space-y-3 border-t pt-5">
            <div class="flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-bold">Carousel items</h2><p class="text-xs text-gray-500">Drag cards to reorder. External image/link URLs must use HTTPS.</p></div><button type="button" id="add-carousel-item" class="rounded-lg border px-3 py-2 text-sm">+ Add item</button></div>
            <div id="carousel-items" class="space-y-3">
                @foreach(old('items', $carousel->items ?? collect()) as $i=>$item)
                    <div class="carousel-item-row grid gap-2 rounded-lg border bg-gray-50 p-3 sm:grid-cols-2" draggable="true">
                        <div class="flex items-center justify-between sm:col-span-2"><strong class="text-sm">Item <span class="item-number">{{ $loop->iteration }}</span></strong><button type="button" class="remove-carousel-item text-sm text-red-700">Remove</button></div>
                        <label class="text-xs font-semibold">Title<input name="items[{{ $i }}][title]" value="{{ data_get($item,'title') }}" maxlength="180" class="mt-1 w-full rounded border p-2"></label>
                        <label class="text-xs font-semibold">Description<textarea name="items[{{ $i }}][description]" maxlength="2000" rows="2" class="mt-1 w-full rounded border p-2">{{ data_get($item,'description') }}</textarea></label>
                        <label class="text-xs font-semibold">Image URL<input name="items[{{ $i }}][image_url]" value="{{ data_get($item,'image_url') }}" maxlength="2048" placeholder="/storage/..." class="mt-1 w-full rounded border p-2"></label>
                        <label class="text-xs font-semibold">Click-through URL<input name="items[{{ $i }}][link_url]" value="{{ data_get($item,'link_url') }}" maxlength="2048" placeholder="/courses/..." class="mt-1 w-full rounded border p-2"></label>
                        <input type="hidden" name="items[{{ $i }}][sort_order]" value="{{ data_get($item,'sort_order',$i) }}" class="item-sort">
                    </div>
                @endforeach
            </div>
            <p id="no-items" class="rounded-lg border border-dashed p-5 text-center text-sm text-gray-500">No manual items added yet.</p>
        </section>
        <div class="flex flex-wrap gap-3 border-t pt-5"><button class="rounded-lg bg-blue-700 px-5 py-2 font-semibold text-white">Save carousel</button><a href="{{ route('admin.carousels.index') }}" class="rounded-lg border px-5 py-2">Cancel</a></div>
    </form>
</div>
<script>
(() => {
 const source=document.getElementById('carousel-source'), list=document.getElementById('carousel-items'), add=document.getElementById('add-carousel-item'), empty=document.getElementById('no-items');
 const sync=()=>{const manual=source.value==='manual';document.getElementById('manual-items').hidden=!manual;add.disabled=!manual;empty.hidden=list.children.length>0||!manual;[...list.children].forEach((row,i)=>{row.querySelector('.item-number').textContent=i+1;row.querySelector('.item-sort').value=i;});};
 const reindex=()=>{[...list.children].forEach((row,i)=>{row.querySelectorAll('input,textarea').forEach(field=>{field.name=field.name.replace(/items\[\d+\]/,'items['+i+']');});});sync();};
 add.addEventListener('click',()=>{if(list.children.length>=30)return;const i=list.children.length,row=document.createElement('div');row.className='carousel-item-row grid gap-2 rounded-lg border bg-gray-50 p-3 sm:grid-cols-2';row.draggable=true;row.innerHTML='<div class="flex items-center justify-between sm:col-span-2"><strong class="text-sm">Item <span class="item-number"></span></strong><button type="button" class="remove-carousel-item text-sm text-red-700">Remove</button></div><label class="text-xs font-semibold">Title<input required name="items['+i+'][title]" maxlength="180" class="mt-1 w-full rounded border p-2"></label><label class="text-xs font-semibold">Description<textarea name="items['+i+'][description]" maxlength="2000" rows="2" class="mt-1 w-full rounded border p-2"></textarea></label><label class="text-xs font-semibold">Image URL<input name="items['+i+'][image_url]" maxlength="2048" placeholder="/storage/..." class="mt-1 w-full rounded border p-2"></label><label class="text-xs font-semibold">Click-through URL<input name="items['+i+'][link_url]" maxlength="2048" placeholder="/courses/..." class="mt-1 w-full rounded border p-2"></label><input type="hidden" name="items['+i+'][sort_order]" value="'+i+'" class="item-sort">';list.appendChild(row);sync();});
 list.addEventListener('click',e=>{if(e.target.classList.contains('remove-carousel-item')){e.target.closest('.carousel-item-row').remove();reindex();}});
 let dragged=null;list.addEventListener('dragstart',e=>{dragged=e.target.closest('.carousel-item-row');if(dragged)e.dataTransfer.effectAllowed='move';});list.addEventListener('dragover',e=>{const target=e.target.closest('.carousel-item-row');if(!dragged||!target||target===dragged)return;e.preventDefault();const box=target.getBoundingClientRect();list.insertBefore(dragged,e.clientY<box.top+box.height/2?target:target.nextSibling);});list.addEventListener('drop',e=>{e.preventDefault();reindex();});list.addEventListener('dragend',()=>{dragged=null;reindex();});source.addEventListener('change',sync);sync();
})();
</script>
@endsection
