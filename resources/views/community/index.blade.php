@extends('layouts.app')

@section('title', 'Community | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <h1>Community · For You</h1>
        <p class="muted">Learn together, share progress, and follow your teachers.</p>
    </div>
</div>

<section class="panel" style="max-width:850px;margin-bottom:18px;">
    <form method="POST" action="{{ route('community.posts.store') }}">
        @csrf
        <label for="post-body">Share something with the learning community</label>
        <textarea id="post-body" name="body" rows="3" maxlength="5000" required placeholder="Write in Bangla or English...">{{ old('body') }}</textarea>
        @error('body')<p class="flash error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit" style="margin-top:10px;">Publish post</button>
    </form>
</section>

<div style="max-width:850px;">
@forelse($posts as $post)
    <article class="panel" style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <div>
                <strong>{{ $post->user->name }}</strong>
                <span class="badge info">{{ ucfirst($post->user->role) }}</span>
                <div class="muted" style="font-size:.8rem;">{{ $post->created_at->diffForHumans() }}</div>
            </div>
            @if(auth()->user()->isStudent() && $post->user->role === 'teacher' && $post->user_id !== auth()->id())
                @if(in_array($post->user_id, $followedIds, true))
                    <form method="POST" action="{{ route('community.teachers.unfollow', $post->user_id) }}">@csrf @method('DELETE')<button class="btn secondary" type="submit">Following</button></form>
                @else
                    <form method="POST" action="{{ route('community.teachers.follow', $post->user_id) }}">@csrf<button class="btn secondary" type="submit">Follow teacher</button></form>
                @endif
            @endif
        </div>
        <p style="white-space:pre-wrap;line-height:1.7;margin:16px 0;">{{ $post->body }}</p>
        <div class="muted" style="font-size:.88rem;">{{ $post->likes_count }} likes · {{ $post->comments_count }} comments</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
            <form method="POST" action="{{ route('community.posts.like', $post) }}">@csrf<button class="btn secondary" type="submit">{{ in_array($post->id, $likedPostIds, true) ? 'Unlike' : 'Like' }}</button></form>
        </div>
        <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px;">
            @foreach($post->comments as $comment)
                <p style="margin:8px 0;"><strong>{{ $comment->user->name }}:</strong> {{ $comment->body }}</p>
            @endforeach
            <form method="POST" action="{{ route('community.posts.comment', $post) }}" style="display:flex;gap:8px;margin-top:10px;">
                @csrf
                <input name="body" maxlength="2000" required placeholder="Write a comment..." aria-label="Comment">
                <button class="btn primary" type="submit">Comment</button>
            </form>
        </div>
    </article>
@empty
    <div class="panel"><p>No community posts yet. Be the first to share a learning update.</p></div>
@endforelse
{{ $posts->links() }}
</div>
@endsection
