@extends('layouts.app')

@section('title', 'Messages | ' . config('app.name'))

@section('content')
<div class="page-header"><div><h1>Messages</h1><p class="muted">Your private conversations.</p></div></div>
<div class="panel" style="max-width:850px;">
@forelse($users as $user)
    <a href="{{ route('community.messages.show', $user) }}" style="display:flex;justify-content:space-between;padding:14px 4px;border-bottom:1px solid var(--border);text-decoration:none;">
        <span><strong>{{ $user->name }}</strong><span class="badge info">{{ ucfirst($user->role) }}</span></span>
        <span class="muted">Open conversation →</span>
    </a>
@empty
    <p>No conversations yet.</p>
@endforelse
</div>
@endsection
