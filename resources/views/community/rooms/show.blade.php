@extends('layouts.app')

@section('title', $room->name . ' | Discussion Club')

@section('content')
<div class="page-header"><div><h1>{{ $room->name }}</h1><p class="muted">{{ $room->description }}</p></div><a class="btn secondary" href="{{ route('community.rooms.index') }}">All rooms</a></div>
<section class="panel" style="max-width:850px;">
    <div style="max-height:55vh;overflow:auto;padding:8px 4px;">
    @forelse($messages as $message)
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <strong>{{ $message->user->name }}</strong><span class="muted"> · {{ $message->created_at->diffForHumans() }}</span>
            <p style="white-space:pre-wrap;margin:6px 0;">{{ $message->body }}</p>
        </div>
    @empty
        <p>No messages yet. Start the discussion.</p>
    @endforelse
    </div>
    <form method="POST" action="{{ route('community.rooms.message', $room) }}" style="margin-top:14px;">
        @csrf
        <label for="room-message">Message</label>
        <textarea id="room-message" name="body" rows="3" maxlength="3000" required></textarea>
        <button class="btn primary" type="submit" style="margin-top:8px;">Send message</button>
    </form>
    <p class="muted" style="margin-top:10px;">Messages currently refresh when you reload the room. Real-time WebSocket delivery will be added after infrastructure and authorization tests.</p>
</section>
@endsection
