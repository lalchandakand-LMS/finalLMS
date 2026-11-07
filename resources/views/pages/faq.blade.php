@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="panel" style="max-width:900px;margin:0 auto;">
        <span class="badge info">{{ config('app.name') }}</span>
        <h1>{{ $title }}</h1>
        @forelse($faqs as $faq)
            <article style="padding:18px 0;border-bottom:1px solid var(--border);">
                <h3 style="margin:0 0 8px;">{{ $faq['question'] }}</h3>
                <p class="muted" style="margin:0;white-space:pre-line;line-height:1.7;">{{ $faq['answer'] }}</p>
            </article>
        @empty
            <p class="muted">{{ __('Frequently asked questions will be published here soon.') }}</p>
        @endforelse
    </section>
@endsection
