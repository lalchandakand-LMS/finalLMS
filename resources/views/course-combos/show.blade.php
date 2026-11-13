@extends('layouts.app')

@section('title', $courseCombo->title . ' | ' . config('app.name'))

@section('content')
    @php($regularPrice = (float) $courseCombo->courses->sum('price'))
    <div class="page-header">
        <div><span class="badge info">{{ __('Course combo') }}</span><h1 style="margin:12px 0 0;">{{ $courseCombo->title }}</h1><p class="muted">{{ $courseCombo->description }}</p></div>
        <a class="btn secondary" href="{{ route('course-combos.index') }}">{{ __('All combos') }}</a>
    </div>
    <div class="grid grid-2" style="align-items:start;">
        <section class="panel">
            <h2 style="margin-top:0;">{{ __('Included courses') }}</h2>
            <div class="course-list">
                @foreach($courseCombo->courses as $course)
                    <article class="course-item" style="display:flex;gap:14px;align-items:center;margin-bottom:12px;">
                        @if($course->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:112px;aspect-ratio:16/9;object-fit:cover;border-radius:9px;">@endif
                        <div style="min-width:0;flex:1;"><strong><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></strong><p class="muted" style="margin:5px 0 0;">{{ $course->teacher?->name ?? __('Instructor') }} · ৳{{ number_format((float) $course->price, 2) }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
        <aside class="panel">
            <span class="muted">{{ __('Combo price') }}</span>
            <h2 style="font-size:2rem;margin:5px 0;">৳{{ number_format((float) $courseCombo->price, 2) }}</h2>
            @if($regularPrice > (float) $courseCombo->price)
                <p class="muted"><del>৳{{ number_format($regularPrice, 2) }}</del> · {{ __('You save') }} ৳{{ number_format($regularPrice - (float) $courseCombo->price, 2) }}</p>
            @endif
            <p class="muted">{{ $courseCombo->courses->count() }} {{ __('courses in this combo') }}</p>
            @if(!$purchaseAvailable)
                <div class="flash warning" role="status">{{ __('This combo cannot be purchased while an included course has an open batch. Please contact the academy or enroll in the course individually.') }}</div>
            @elseif(auth()->check() && auth()->user()->isStudent())
                <form method="POST" action="{{ route('course-combos.enroll', $courseCombo) }}">
                    @csrf
                    <button class="btn primary" type="submit" style="width:100%;">{{ $courseCombo->price <= 0 ? __('Enroll in all courses') : __('Buy this combo') }}</button>
                </form>
            @elseif(auth()->guest())
                <a class="btn primary" style="display:block;text-align:center;" href="{{ route('login', ['continue' => route('course-combos.show', $courseCombo)]) }}">{{ __('Login to buy this combo') }}</a>
            @else
                <p class="muted">{{ __('Only student accounts can purchase course combos.') }}</p>
            @endif
            <p class="muted" style="font-size:.82rem;margin:14px 0 0;">{{ __('Paid combo access is granted after the academy verifies the payment. All included courses are added to My Courses together.') }}</p>
        </aside>
    </div>
@endsection
