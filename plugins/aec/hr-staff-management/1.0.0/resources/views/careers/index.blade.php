@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <header><h1 class="text-3xl font-bold">Careers at Alom’s English Center</h1><p class="mt-2 text-gray-600">Explore current opportunities and apply to join our academic and operations team.</p></header>
    @if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($openings as $opening)
            <article class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="flex flex-wrap gap-2 text-xs"><span class="rounded bg-blue-50 px-2 py-1 text-blue-800">{{ str_replace('_',' ',ucfirst($opening->employment_type)) }}</span>@if($opening->location)<span class="rounded bg-gray-100 px-2 py-1">{{ $opening->location }}</span>@endif</div>
                <h2 class="mt-3 text-xl font-semibold">{{ $opening->title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $opening->department }} · {{ $opening->designation }}</p>
                <p class="mt-3 line-clamp-3 text-sm text-gray-700">{{ \Illuminate\Support\Str::limit(strip_tags($opening->description), 220) }}</p>
                <p class="mt-3 text-xs text-gray-500">Applications close: {{ $opening->closes_at?->format('d M Y, h:i A') ?? 'Until filled' }}</p>
                <a class="mt-4 inline-flex rounded bg-blue-700 px-4 py-2 text-white" href="{{ route('careers.show', $opening) }}">View details & apply</a>
            </article>
        @empty
            <div class="rounded border bg-white p-8 text-center text-gray-600 md:col-span-2">There are no open positions at the moment. Please check again later.</div>
        @endforelse
    </div>
    {{ $openings->links() }}
</div>
@endsection
