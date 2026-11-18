@extends('layouts.app')

@section('title', __('Exams') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Learn & Earn') }}</span><h1>{{ __('Available exams') }}</h1><p class="muted">{{ __('Explore upcoming and currently available academy exams.') }}</p></div>
</div>
<div class="grid grid-2">
    @forelse($exams as $exam)
        <article class="panel">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <h2 style="margin:0;">{{ $exam->title }}</h2>
                <span class="badge {{ $exam->access_type === 'free' ? 'success' : 'warning' }}">{{ $exam->access_type === 'free' ? __('Free') : __('Paid') }}</span>
            </div>
            <p class="muted">{{ \Illuminate\Support\Str::limit($exam->description, 180) }}</p>
            <div class="meta-row" style="flex-wrap:wrap;gap:10px;">
                <small>{{ __('Starts') }}: {{ $exam->starts_at->format('d M Y, h:i A') }}</small>
                <small>{{ __('Duration') }}: {{ $exam->duration_minutes }} {{ __('minutes') }}</small>
                <small>{{ __('Attempts') }}: {{ $exam->max_attempts }}</small>
                @if($exam->access_type === 'paid')<small>{{ __('Price') }}: ৳{{ number_format((float)$exam->price, 2) }}</small>@endif
            </div>
            <a class="btn secondary" href="{{ route('learn-earn.exams.show', $exam) }}">{{ __('View exam') }}</a>
        </article>
    @empty
        <div class="panel muted">{{ __('There are no published exams available right now.') }}</div>
    @endforelse
</div>
<div style="margin-top:18px;">{{ $exams->links() }}</div>
@endsection
