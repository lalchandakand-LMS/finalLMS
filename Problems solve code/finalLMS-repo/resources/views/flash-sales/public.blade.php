@extends('layouts.app')
@section('title', 'Flash Sale | '.config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge warning">🔥 Limited-time offers</span><h1>Flash Sale</h1><p class="muted">Special course offers currently available from Alom’s English Center.</p></div></div>
@if($campaigns->isEmpty())
    <div class="panel"><h2>No active offers right now</h2><p class="muted">Please check again soon for upcoming course discounts.</p><a class="btn primary" href="{{ route('courses.index') }}">Explore all courses</a></div>
@else
    @foreach($campaigns as $sale)
        <section class="panel" style="margin-bottom:24px;">
            @if($sale->banner_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($sale->banner_path) }}" alt="{{ $sale->title }}" loading="lazy" style="width:100%;max-height:320px;object-fit:cover;border-radius:16px;">@endif
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center;margin:16px 0;">
                <div><span class="badge warning">{{ $sale->badge }}</span><h2>{{ $sale->title }}</h2><p class="muted">{{ $sale->description }}</p></div>
                <strong>Offer ends {{ $sale->ends_at->format('d M Y, h:i A') }}</strong>
            </div>
            <div class="grid grid-3">
                @foreach($sale->displayCourses as $course)
                    @php($discounted = $sale->discountedPrice((float) $course->price))
                    <article class="card">
                        @if($course->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;height:160px;object-fit:cover;border-radius:12px;">@endif
                        <h3>{{ $course->title }}</h3>
                        <p><s class="muted">৳{{ number_format((float)$course->price, 2) }}</s> <strong>৳{{ number_format($discounted, 2) }}</strong></p>
                        <a class="btn primary" href="{{ route('courses.show', $course) }}">View course</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
@endif
@endsection
