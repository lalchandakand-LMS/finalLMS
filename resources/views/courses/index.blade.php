@extends('layouts.app')

@section('title', __('Courses') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">{{ __('Catalog') }}</span><h1 style="margin:12px 0 0;">{{ __('Available courses') }}</h1></div>
        <span class="badge success">{{ $courses->total() }} {{ __('courses found') }}</span>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <form method="GET" action="{{ route('courses.index') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end;">
            <label style="grid-column:span 2;">{{ __('Search courses') }}<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="{{ __('Title, description or tag') }}"></label>
            <label>{{ __('Course category') }}<select name="category"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></label>
            <label>{{ __('Level') }}<select name="level"><option value="">{{ __('All levels') }}</option>@foreach(['Beginner','Elementary','Intermediate','Upper-Intermediate','Advanced','All levels'] as $level)<option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ __($level) }}</option>@endforeach</select></label>
            <label>{{ __('Language') }}<select name="language"><option value="">{{ __('All languages') }}</option>@foreach(['Bangla','English','Bangla & English'] as $language)<option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ __($language) }}</option>@endforeach</select></label>
            <label>{{ __('Minimum price') }}<input type="number" name="min_price" min="0" step="0.01" value="{{ $filters['min_price'] ?? '' }}"></label>
            <label>{{ __('Maximum price') }}<input type="number" name="max_price" min="0" step="0.01" value="{{ $filters['max_price'] ?? '' }}"></label>
            <label>{{ __('Sort by') }}<select name="sort"><option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>{{ __('Latest') }}</option><option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>{{ __('Price: low to high') }}</option><option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>{{ __('Price: high to low') }}</option><option value="title" @selected(($filters['sort'] ?? '') === 'title')>{{ __('Course title') }}</option></select></label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;"><button class="btn primary">{{ __('Apply filters') }}</button><a href="{{ route('courses.index') }}" class="btn secondary">{{ __('Reset') }}</a></div>
        </form>
    </div>

    @if($courses->isEmpty())
        <div class="panel"><p class="muted">{{ __('No courses match your filters. Try changing your search criteria.') }}</p></div>
    @else
        <div class="course-list">
            @foreach($courses as $course)
                <div class="course-item">
                    @if($course->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;max-height:240px;aspect-ratio:16/9;object-fit:cover;border-radius:12px;margin-bottom:14px;">@endif
                    <div class="meta-row">
                        <div style="min-width:0;">
                            <h3 style="margin:0 0 8px;">{{ $course->title }}</h3>
                            <p class="muted" style="margin:0 0 12px;">{{ $course->short_description ?: \Illuminate\Support\Str::limit(strip_tags($course->description), 160) }}</p>
                            @if($course->categories->isNotEmpty())<div class="meta-row" style="margin-bottom:8px;">@foreach($course->categories as $category)<span class="badge info">{{ $category->name }}</span>@endforeach</div>@endif
                            <div class="muted">{{ __('Teacher:') }} {{ $course->teacher?->name ?? __('N/A') }} · {{ __('Price:') }} BDT {{ number_format($course->price, 2) }}
                                @if($course->level) · {{ __($course->level) }} @endif
                                @if($course->language) · {{ __($course->language) }} @endif
                            </div>
                        </div>
                        <div><a href="{{ route('courses.show', $course) }}" class="btn secondary">{{ __('View details') }}</a></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="margin-top:18px;">{{ $courses->links() }}</div>
    @endif
@endsection
