@extends('layouts.app')

@section('title', $exam->title . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Learn & Earn') }}</span><h1>{{ $exam->title }}</h1><p class="muted">{{ $exam->description }}</p></div>
    <a class="btn secondary" href="{{ route('learn-earn.exams.index') }}">{{ __('All exams') }}</a>
</div>
<div class="panel">
    <div class="grid grid-3">
        <div><small class="muted">{{ __('Access') }}</small><h3>{{ $exam->access_type === 'free' ? __('Free') : __('Paid') }}</h3></div>
        <div><small class="muted">{{ __('Duration') }}</small><h3>{{ $exam->duration_minutes }} {{ __('minutes') }}</h3></div>
        <div><small class="muted">{{ __('Maximum attempts') }}</small><h3>{{ $exam->max_attempts }}</h3></div>
        @if($exam->access_type === 'paid')
            <div><small class="muted">{{ __('Price') }}</small><h3>৳{{ number_format((float)$exam->price, 2) }}</h3></div>
        @endif
        <div><small class="muted">{{ __('Starts') }}</small><h3>{{ $exam->starts_at->format('d M Y, h:i A') }}</h3></div>
        <div><small class="muted">{{ __('Ends') }}</small><h3>{{ $exam->ends_at->format('d M Y, h:i A') }}</h3></div>
    </div>
    <p class="muted">{{ __('Question details and answer keys are not shown before an attempt. Sign in with your student account to continue.') }}</p>
    @auth
        @if(auth()->user()->isStudent())
            <a class="btn primary" href="{{ route('student.learn-earn.exams.show', $exam) }}">{{ __('Continue as student') }}</a>
        @else
            <span class="badge warning">{{ __('Student account required') }}</span>
        @endif
    @else
        <a class="btn primary" href="{{ route('login') }}">{{ __('Student login') }}</a>
    @endauth
</div>
@endsection
