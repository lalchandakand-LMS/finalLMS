@extends('layouts.app')

@section('title', 'Discussion Club | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><h1>Discussion Club</h1><p class="muted">Join a learning room and discuss with your community.</p></div>
</div>

@if(in_array(auth()->user()->role, ['admin', 'teacher'], true))
<section class="panel" style="max-width:850px;margin-bottom:18px;">
    <h2>Create a discussion room</h2>
    <form method="POST" action="{{ route('community.rooms.store') }}">
        @csrf
        <label>Room name</label><input name="name" maxlength="120" required>
        <label>Description</label><textarea name="description" maxlength="1500" rows="2"></textarea>
        <label>Access</label><select name="visibility"><option value="public">Public — any signed-in member can join</option><option value="private">Private — owner only until invite support is added</option></select>
        <button class="btn primary" type="submit" style="margin-top:10px;">Create room</button>
    </form>
</section>
@endif

<div style="max-width:850px;">
@forelse($rooms as $room)
<article class="panel" style="margin-bottom:12px;">
    <h2>{{ $room->name }}</h2>
    <p>{{ $room->description }}</p>
    <p class="muted">{{ $room->members_count }} members · Hosted by {{ $room->owner->name }}</p>
    <span class="badge info">{{ ucfirst($room->visibility) }}</span>
    @if($room->visibility === 'public' || $room->created_by === auth()->id())
    <form method="POST" action="{{ route('community.rooms.join', $room) }}" style="margin-top:12px;">@csrf<button class="btn primary" type="submit">Join / Open room</button></form>
    @else
    <p class="muted">Private room. Ask the owner for access.</p>
    @endif
</article>
@empty
<div class="panel">No discussion rooms yet.</div>
@endforelse
{{ $rooms->links() }}
</div>
@endsection
