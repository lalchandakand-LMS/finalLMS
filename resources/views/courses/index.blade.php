@extends('layouts.app')

@section('title', __('Courses') . ' | ' . config('app.name'))

@section('content')
    <style>
        .course-catalog-hero{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:24px;padding:clamp(24px,4vw,42px);border-radius:22px;color:#fff;background:linear-gradient(120deg,#102c52,#176b85 65%,#1c8a8a)}
        .course-catalog-hero h1{margin:8px 0;font-size:clamp(1.8rem,4vw,2.7rem);color:#fff}
        .course-catalog-hero p{max-width:620px;margin:0;color:#d7edf4;line-height:1.7}
        .course-catalog-count{padding:10px 14px;border:1px solid #ffffff50;border-radius:12px;background:#ffffff16;white-space:nowrap}
        .course-filter-panel{margin-bottom:22px}
        .course-filter-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:12px;align-items:end}
        .course-catalog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
        .catalog-course-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid var(--border);border-radius:17px;background:#fff;box-shadow:0 8px 24px rgba(16,42,76,.06)}
        .catalog-course-image{position:relative;aspect-ratio:16/9;overflow:hidden;background:linear-gradient(135deg,#e6f1ff,#d7f3ed)}
        .catalog-course-image img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s}
        .catalog-course-card:hover .catalog-course-image img{transform:scale(1.04)}
        .catalog-course-image__fallback{display:grid;width:100%;height:100%;place-items:center;color:#176b85;font-size:3rem;font-weight:900}
        .catalog-course-body{display:flex;flex:1;flex-direction:column;padding:18px}
        .catalog-course-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
        .catalog-course-tags span{padding:4px 8px;border-radius:30px;background:#edf4fa;color:#31536c;font-size:.72rem;font-weight:700}
        .catalog-course-body h2{margin:0 0 8px;font-size:1.08rem;line-height:1.45}
        .catalog-course-description{min-height:48px;margin:0 0 13px;color:var(--muted);font-size:.84rem;line-height:1.6}
        .catalog-course-meta{display:flex;flex-wrap:wrap;gap:12px;margin:0 0 14px;color:var(--muted);font-size:.78rem}
        .catalog-course-price{display:flex;align-items:baseline;gap:8px;margin-top:auto;padding-top:13px;border-top:1px solid var(--border)}
        .catalog-course-price strong{color:#123e62;font-size:1.25rem}
        .catalog-course-price del{color:#7b8794;font-size:.83rem}
        .catalog-course-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px}
        @media(max-width:950px){.course-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.course-catalog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:600px){.course-catalog-hero{align-items:flex-start;flex-direction:column}.course-filter-grid,.course-catalog-grid{grid-template-columns:1fr}.catalog-course-body{padding:15px}}
    </style>

    <section class="course-catalog-hero">
        <div>
            <span style="font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ce7d8;">{{ __('Explore learning') }}</span>
            <h1>{{ __('Find the right course for you') }}</h1>
            <p>{{ __('Explore our published courses, compare learning options, meet your instructors and choose your next step.') }}</p>
        </div>
        <div class="course-catalog-count"><strong>{{ number_format($courses->total()) }}</strong> {{ __('courses available') }}</div>
    </section>

    <form method="GET" action="{{ route('courses.index') }}" class="panel course-filter-panel">
        <h2 style="margin-top:0;">{{ __('Find your course') }}</h2>
        <div class="course-filter-grid">
            <label>{{ __('Search') }}
                <input type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="{{ __('Course title, topic or teacher') }}">
            </label>
            <label>{{ __('Category') }}
                <select name="category">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Instructor') }}
                <select name="teacher">
                    <option value="">{{ __('All instructors') }}</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) request('teacher') === (string) $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Minimum price (BDT)') }}
                <input type="number" name="min_price" min="0" max="100000000" step="0.01" value="{{ request('min_price') }}">
            </label>
            <label>{{ __('Maximum price (BDT)') }}
                <input type="number" name="max_price" min="0" max="100000000" step="0.01" value="{{ request('max_price') }}">
            </label>
        </div>
        <div class="meta-row" style="margin-top:14px;align-items:end;">
            <label style="max-width:240px;">{{ __('Sort by') }}
                <select name="sort">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Newest first') }}</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Oldest first') }}</option>
                    <option value="price_low" @selected(request('sort') === 'price_low')>{{ __('Price: low to high') }}</option>
                    <option value="price_high" @selected(request('sort') === 'price_high')>{{ __('Price: high to low') }}</option>
                    <option value="title" @selected(request('sort') === 'title')>{{ __('Course title') }}</option>
                </select>
            </label>
            <button class="btn primary" type="submit">{{ __('Apply filters') }}</button>
            <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Clear filters') }}</a>
        </div>
    </form>

    <div class="course-catalog-grid">
        @foreach($courses as $course)
            @php
                $activeFlashSale = \App\Models\FlashSale::activeForCourse($course->id, auth()->id());
                $basePrice = (float) $course->price;
                $offerPrice = $activeFlashSale ? $activeFlashSale->discountedPrice($basePrice) : $basePrice;
                $courseCategories = $course->curriculumItems->flatMap(fn ($item) => $item->post?->categories ?? collect())->unique('id')->take(3);
            @endphp
            <article class="catalog-course-card">
                <a class="catalog-course-image" href="{{ route('courses.show', $course) }}" aria-label="{{ __('View course') }}: {{ $course->title }}">
                    @if($course->image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy">
                    @else
                        <span class="catalog-course-image__fallback" aria-hidden="true">A+</span>
                    @endif
                    @if($activeFlashSale)
                        <span class="badge warning" style="position:absolute;top:12px;left:12px;">{{ $activeFlashSale->badge }}</span>
                    @endif
                </a>
                <div class="catalog-course-body">
                    <div class="catalog-course-tags">
                        @forelse($courseCategories as $category)
                            <span>{{ $category->name }}</span>
                        @empty
                            <span>{{ __('Course') }}</span>
                        @endforelse
                    </div>
                    <h2><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h2>
                    <p class="catalog-course-description">{{ \Illuminate\Support\Str::limit(strip_tags($course->description), 125) }}</p>
                    <div class="catalog-course-meta">
                        <span>♙ {{ $course->teacher?->name ?? __('Instructor to be announced') }}</span>
                        <span>▣ {{ number_format($course->students_count) }} {{ __('students') }}</span>
                    </div>
                    <div class="catalog-course-price">
                        <strong>{{ $offerPrice <= 0 ? __('Free') : '৳'.number_format($offerPrice, 0) }}</strong>
                        @if($activeFlashSale && $offerPrice < $basePrice)
                            <del>৳{{ number_format($basePrice, 0) }}</del>
                        @endif
                    </div>
                    <div class="catalog-course-footer">
                        <small class="muted">{{ ($course->lessons_count ?? 0) + ($course->curriculum_items_count ?? 0) }} {{ __('lessons') }}</small>
                        <a href="{{ route('courses.show', $course) }}" class="btn primary">{{ __('View course') }} →</a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    @if($courses->isEmpty())
        <div class="panel" style="margin-top:20px;text-align:center;">
            <h2>{{ __('No courses found') }}</h2>
            <p class="muted">{{ __('Try changing your search or filters.') }}</p>
            <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Browse all courses') }}</a>
        </div>
    @endif
    <div style="margin-top:22px;">{{ $courses->links() }}</div>
@endsection
