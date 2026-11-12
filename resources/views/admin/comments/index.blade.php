@extends('layouts.app')
@section('title', __('Comment moderation'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Comment moderation') }}</h1><p class="muted">{{ __('Review comments before they appear publicly.') }}</p></div><a class="btn secondary" href="{{ route('admin.analytics.index') }}">{{ __('Analytics') }}</a></div>
<div class="panel">
@forelse($comments as $comment)
<div class="course-item" style="margin-bottom:12px;">
    <div class="meta-row" style="margin-top:0;"><strong>{{ $comment->author?->name ?? __('Deleted user') }}</strong><span class="badge {{ $comment->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($comment->status) }}</span></div>
    <p>{{ $comment->body }}</p>
    <small class="muted">{{ class_basename($comment->commentable_type) }} #{{ $comment->commentable_id }} · {{ $comment->created_at->format('d M Y H:i') }}</small>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
        @foreach(['approved' => __('Approve'), 'rejected' => __('Reject'), 'pending' => __('Set pending')] as $status => $label)
        <form method="POST" action="{{ route('admin.comments.update', $comment) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $status }}"><button class="btn secondary" type="submit">{{ $label }}</button></form>
        @endforeach
        <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('{{ __('Delete this comment?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete') }}</button></form>
    </div>
</div>
@empty<p class="muted">{{ __('No comments found.') }}</p>@endforelse
{{ $comments->links() }}
</div>
@endsection
