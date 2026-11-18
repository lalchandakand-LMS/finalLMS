@extends('layouts.app')

@section('title', 'Chat with ' . $user->name)

@section('content')
<div class="page-header"><div><h1>{{ $user->name }}</h1><p class="muted">Private messages</p></div><a class="btn secondary" href="{{ route('community.messages.index') }}">All messages</a></div>
<section class="panel" style="max-width:850px;">
    <div style="max-height:55vh;overflow:auto;padding:8px 4px;">
    @forelse($messages as $message)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $message->sender->name }}</strong><span class="muted"> · {{ $message->created_at->diffForHumans() }}</span>
            <p style="white-space:pre-wrap;margin:6px 0;">{{ $message->body }}</p>
            @if($message->sender_id === auth()->id() && $message->read_at)<small class="muted">Seen</small>@endif
        </div>
    @empty
        <p>Start a conversation.</p>
    @endforelse
    </div>
    <form method="POST" action="{{ route('community.messages.store', $user) }}" style="margin-top:14px;">
        @csrf
        <label for="direct-message">Message</label>
        <textarea id="direct-message" name="body" rows="3" maxlength="3000" required></textarea>
        <button class="btn primary" type="submit" style="margin-top:8px;">Send</button>
    </form>
    <p class="muted" style="margin-top:10px;">Messages are private to the two participants. Automatic live updates will be enabled with the realtime service.</p>
</section>
@endsection
