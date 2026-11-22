<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-bold">Carousel Builder</h1><p class="text-sm text-gray-600">Create reusable, responsive website carousels from curated items, courses, teachers or approved student testimonials.</p></div>
            <a href="{{ route('admin.carousels.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Create carousel</a>
        </div>
        @if(session('success'))<p class="rounded-lg bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm"><thead class="bg-gray-50 text-left"><tr><th class="p-3">Carousel</th><th class="p-3">Source</th><th class="p-3">Items</th><th class="p-3">Layout</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead><tbody>
            @forelse($carousels as $carousel)
                <tr class="border-t"><td class="p-3"><strong>{{ $carousel->title }}</strong><small class="block text-gray-500">/{{ $carousel->slug }} · order {{ $carousel->sort_order }}</small></td><td class="p-3">{{ ucfirst($carousel->source_type) }}</td><td class="p-3">{{ $carousel->items_count }}</td><td class="p-3">{{ $carousel->desktop_cards }} desktop / {{ $carousel->mobile_cards }} mobile</td><td class="p-3"><span class="rounded-full px-2 py-1 {{ $carousel->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($carousel->status) }}</span></td><td class="whitespace-nowrap p-3"><a class="text-blue-700" href="{{ route('admin.carousels.edit',$carousel->id) }}">Edit</a><form class="ml-3 inline" method="POST" action="{{ route('admin.carousels.destroy',$carousel->id) }}" onsubmit="return confirm('Delete this carousel?')">@csrf @method('DELETE')<button class="text-red-700">Delete</button></form></td></tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-gray-500">No carousels yet. Create your first reusable carousel.</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $carousels->links() }}
        <div class="rounded-xl border bg-blue-50 p-4 text-sm text-blue-950"><strong>How to use:</strong> Publish a carousel and it will appear on the homepage. Manual items are fully editable; dynamic sources refresh from published courses, active teacher profiles and approved course testimonials.</div>
    </div>
</x-app-layout>