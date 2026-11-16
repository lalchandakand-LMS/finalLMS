@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="panel" style="max-width:900px;margin:0 auto;">
        <span class="badge info">{{ config('app.name') }}</span>
        <h1>{{ __($title) }}</h1>
        @if($body)
            <div style="white-space:pre-line;line-height:1.8;">{{ $body }}</div>
        @else
            <p class="muted">{{ __('This page is being updated. Please contact the learning center for more information.') }}</p>
        @endif
    </section>
@endsection
