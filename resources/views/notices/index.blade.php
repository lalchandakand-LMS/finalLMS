@extends('layouts.app')

@section('title', __('Notice Board') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Academy communication') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Notice Board') }}</h1>
            <p class="muted">{{ __('Official notices for your account type and assigned branch.') }}</p>
        </div>
        <span class="badge {{ $unreadCount > 0 ? 'warning' : 'success' }}">{{ number_format($unreadCount) }} {{ __('unread') }}</span>
    </div>

    <div class="course-list">
        @forelse($announcements as $announcement)
            <article class="panel" style="margin-bottom:14px;">
                <div class="page-header" style="margin-bottom:8px;">
                    <div>
                        <span class="badge info">{{ __(ucfirst(str_replace('_', ' ', $announcement->audience))) }}</span>
                        @if($announcement->branch)
                            <span class="badge info">{{ $announcement->branch->name }}</span>
                        @else
                            <span class="badge info">{{ __('All branches') }}</span>
                        @endif
                        <span class="badge {{ $announcement->is_read ? 'success' : 'warning' }}">{{ $announcement->is_read ? __('Read') : __('Unread') }}</span>
                    </div>
                    <small class="muted">{{ $announcement->created_at->format('d M Y') }}</small>
                </div>
                <h2 style="margin:8px 0;">{{ $announcement->title }}</h2>
                @if($announcement->image)
                    <img src="{{ $announcement->image->url }}" alt="{{ $announcement->title }}" loading="lazy" style="width:100%;max-height:240px;object-fit:cover;border-radius:12px;margin:8px 0;">
                @endif
                @if($announcement->body)
                    <p style="white-space:pre-line;line-height:1.7;">{{ $announcement->body }}</p>
                @endif
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px;">
                    @if($announcement->button_url)
                        <a class="btn primary" href="{{ $announcement->button_url }}">{{ $announcement->button_label ?: __('View details') }}</a>
                    @endif
                    @unless($announcement->is_read)
                        <form method="POST" action="{{ route('notices.read', $announcement) }}">
                            @csrf
                            <button class="btn secondary" type="submit">{{ __('Mark as read') }}</button>
                        </form>
                    @endunless
                    @if($announcement->starts_at || $announcement->ends_at)
                        <small class="muted">{{ $announcement->starts_at?->format('d M Y H:i') ?? __('Start now') }} – {{ $announcement->ends_at?->format('d M Y H:i') ?? __('No expiry') }}</small>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">{{ __('There are no active notices for your account right now.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:18px;">{{ $announcements->links() }}</div>
@endsection
