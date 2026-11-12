@extends('layouts.app')

@section('title', __('My Wishlist') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('My learning') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('My Wishlist') }}</h1>
        </div>
        <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Browse courses') }}</a>
    </div>

    @if($courses->isEmpty())
        <div class="panel">
            <h2>{{ __('Your wishlist is empty') }}</h2>
            <p class="muted">{{ __('Save courses you are interested in and find them here later.') }}</p>
            <a class="btn primary" href="{{ route('courses.index') }}">{{ __('Explore courses') }}</a>
        </div>
    @else
        <div class="course-list">
            @foreach($courses as $course)
                <article class="course-item">
                    @if($course->image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;max-height:220px;aspect-ratio:16/9;object-fit:cover;border-radius:12px;margin-bottom:14px;">
                    @endif
                    <div class="meta-row">
                        <div>
                            <h2 style="margin:0 0 8px;">{{ $course->title }}</h2>
                            <p class="muted">{{ \Illuminate\Support\Str::limit($course->description, 150) }}</p>
                            <p class="muted">{{ __('Teacher:') }} {{ $course->teacher?->name ?? __('N/A') }} · BDT {{ number_format($course->price, 2) }}</p>
                        </div>
                        <div>
                            <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('View course') }}</a>
                            <form method="POST" action="{{ route('student.wishlist.destroy', $course) }}" style="margin-top:8px;">
                                @csrf
                                @method('DELETE')
                                <button class="btn secondary" type="submit">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div style="margin-top:20px;">{{ $courses->links() }}</div>
    @endif
@endsection
