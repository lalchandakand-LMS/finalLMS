@extends('layouts.app')

@section('title', __('Courses') . ' | ' . config('app.name'))

@section('content')
    @php
        $courseCount = $courses->total();
    @endphp

    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Catalog') }}</span>
            <h1 style="margin: 12px 0 0;">{{ __('Available courses') }}</h1>
        </div>
        <span class="badge success">{{ $courseCount }} {{ __('online') }}</span>
    </div>

    <form method="GET" action="{{ route('courses.index') }}" class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Find your course') }}</h2>
        <div class="grid grid-2">
            <label>{{ __('Search courses or instructors') }}
                <input type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="{{ __('Course title, topic or teacher') }}">
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
            <label>{{ __('Sort by') }}
                <select name="sort">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Newest first') }}</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Oldest first') }}</option>
                    <option value="price_low" @selected(request('sort') === 'price_low')>{{ __('Price: low to high') }}</option>
                    <option value="price_high" @selected(request('sort') === 'price_high')>{{ __('Price: high to low') }}</option>
                    <option value="title" @selected(request('sort') === 'title')>{{ __('Course title') }}</option>
                </select>
            </label>
        </div>
        <div class="meta-row" style="margin-top:14px;">
            <button class="btn primary" type="submit">{{ __('Apply filters') }}</button>
            <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Clear filters') }}</a>
        </div>
    </form>

    <div class="dash-summary" style="margin-bottom: 20px;">
        <div class="summary-box">
            <span class="muted">{{ __('Available') }}</span>
            <strong>{{ $courseCount }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Topics') }}</span>
            <strong>{{ $courseCount }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Access') }}</span>
            <strong>24/7</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Format') }}</span>
            <strong>{{ __('Self-paced') }}</strong>
        </div>
    </div>

    <div class="course-list">
        @foreach($courses as $course)
            <div class="course-item">
                @if($course->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;max-height:240px;aspect-ratio:16/9;object-fit:cover;border-radius:12px;margin-bottom:14px;">@endif
                <div class="meta-row">
                    <div>
                        <div style="margin-bottom: 8px;">
                            <span class="badge {{ $course->status === 'published' ? 'success' : 'warning' }}">{{ __(ucfirst($course->status)) }}</span>
                        </div>
                        <h3 style="margin: 0 0 8px;">{{ $course->title }}</h3>
                        <p class="muted" style="margin: 0 0 12px;">{{ \Illuminate\Support\Str::limit($course->description, 160) }}</p>
                        <div class="muted">{{ __('Teacher:') }} {{ $course->teacher?->name ?? __('N/A') }} · {{ __('Price:') }} BDT {{ number_format($course->price, 2) }}</div>
                    </div>
                    <div>
                        <a href="{{ route('courses.show', $course) }}" class="btn secondary">{{ __('View details') }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($courses->isEmpty())
        <div class="panel"><h2>{{ __('No courses found') }}</h2><p class="muted">{{ __('Try changing your search or filters.') }}</p></div>
    @endif
    <div style="margin-top:20px;">{{ $courses->links() }}</div>
@endsection
