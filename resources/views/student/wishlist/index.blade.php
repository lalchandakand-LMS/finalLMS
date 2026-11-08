@extends('layouts.app')

@section('title', __('My Wishlist') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Saved for later') }}</span>
        <h1 style="margin:10px 0 0;">{{ __('My Wishlist') }}</h1>
        <p class="muted">{{ __('Keep track of courses you may want to join.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('courses.index') }}">{{ __('Browse courses') }}</a>
</div>

<div class="grid grid-2">
    @forelse($courses as $course)
        <article class="panel">
            @if($course->image_path)
                <img src="{{ asset('storage/' . $course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;max-height:190px;object-fit:cover;border-radius:12px;">
            @endif
            <h2><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h2>
            <p class="muted">{{ __('Instructor:') }} {{ $course->teacher?->name ?? __('N/A') }}</p>
            <p>{{ \Illuminate\Support\Str::limit($course->description, 160) }}</p>
            <div class="meta-row" style="justify-content:space-between;">
                <strong>BDT {{ number_format((float) $course->price, 2) }}</strong>
                <form method="POST" action="{{ route('student.wishlist.destroy', $course) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn secondary" type="submit">{{ __('Remove') }}</button>
                </form>
            </div>
            <a class="btn primary" style="margin-top:12px;" href="{{ route('courses.show', $course) }}">{{ __('View course') }}</a>
        </article>
    @empty
        <div class="panel">
            <h2>{{ __('Your wishlist is empty') }}</h2>
            <p class="muted">{{ __('Save interesting courses here and return when you are ready.') }}</p>
            <a class="btn primary" href="{{ route('courses.index') }}">{{ __('Explore courses') }}</a>
        </div>
    @endforelse
</div>
<div style="margin-top:20px;">{{ $courses->links() }}</div>
@endsection
