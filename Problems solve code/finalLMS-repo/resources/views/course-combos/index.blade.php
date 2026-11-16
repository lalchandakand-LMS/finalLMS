@extends('layouts.app')

@section('title', __('Course combos') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ __('Save with a learning bundle') }}</span><h1 style="margin:12px 0 0;">{{ __('Course combos') }}</h1><p class="muted">{{ __('Learn more together with selected courses in one package.') }}</p></div>
        <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Browse all courses') }}</a>
    </div>
    <div class="grid grid-3">
        @forelse($combos as $combo)
            @php
                $firstCourse = $combo->courses->first();
                $regularPrice = (float) $combo->courses->sum('price');
                $available = $combo->courses->every(fn ($course) => (int) $course->open_batches_count === 0);
            @endphp
            <article class="panel" style="display:flex;flex-direction:column;gap:12px;">
                @if($firstCourse?->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($firstCourse->image_path) }}" alt="{{ $combo->title }}" loading="lazy" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:12px;">
                @else
                    <div style="display:grid;place-items:center;aspect-ratio:16/9;border-radius:12px;background:linear-gradient(120deg,#e4efff,#d8f4eb);color:#175c78;font-size:2.5rem;font-weight:900;">A+</div>
                @endif
                <div><h2 style="margin:0 0 6px;">{{ $combo->title }}</h2><p class="muted" style="margin:0;">{{ \Illuminate\Support\Str::limit($combo->description, 150) }}</p></div>
                <div class="meta-row"><span>{{ $combo->courses->count() }} {{ __('courses included') }}</span><span><strong>৳{{ number_format((float) $combo->price, 2) }}</strong></span></div>
                @if($regularPrice > (float) $combo->price)<div class="muted"><del>৳{{ number_format($regularPrice, 2) }}</del> · {{ __('Save') }} ৳{{ number_format($regularPrice - (float) $combo->price, 2) }}</div>@endif
                @if(!$available)<p class="muted" style="margin:0;">{{ __('Temporarily unavailable: one or more included courses has an open batch.') }}</p>@endif
                <a class="btn primary" style="margin-top:auto;" href="{{ route('course-combos.show', $combo) }}">{{ __('View combo') }}</a>
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No course combos are available right now.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $combos->links() }}</div>
@endsection
