@extends('layouts.app')

@section('title', __('Courses') . ' | ' . config('app.name'))

@section('content')
    @php
        $courseCount = $courses->count();
    @endphp

    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Catalog') }}</span>
            <h1 style="margin: 12px 0 0;">{{ __('Available courses') }}</h1>
        </div>
        <span class="badge success">{{ $courseCount }} {{ __('online') }}</span>
    </div>

    <div class="dash-summary" style="margin-bottom: 20px;">
        <div class="summary-box">
            <span class="muted">{{ __('Available') }}</span>
            <strong>{{ $courseCount }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Topics') }}</span>
            <strong>{{ $courses->where('status', 'published')->count() }}</strong>
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
@endsection
