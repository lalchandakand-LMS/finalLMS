@extends('layouts.app')
@section('title', __('Dashboard announcements'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Academy communication') }}</span><h1>{{ __('Dashboard announcement slider') }}</h1><p class="muted">{{ __('Publish course launches, new posts and custom notices to student, teacher and guardian dashboards.') }}</p></div><a class="btn primary" href="{{ route('admin.dashboard-announcements.create') }}">{{ __('Create announcement') }}</a></div>
<div class="panel">
@forelse($announcements as $item)
    <article class="course-item" style="margin-bottom:14px;">
        <div class="page-header" style="margin-bottom:8px;"><div><h3 style="margin:0 0 6px;">{{ $item->title }}</h3><span class="badge info">{{ ucfirst($item->content_type) }}</span> <span class="badge {{ $item->is_active ? 'success' : 'warning' }}">{{ $item->is_active ? __('Active') : __('Inactive') }}</span></div><strong>{{ __('Priority') }}: {{ $item->priority }}</strong></div>
        @if($item->image)<img src="{{ $item->image->url }}" alt="{{ $item->title }}" style="width:100%;max-width:420px;max-height:150px;object-fit:cover;border-radius:12px;">@endif
        <p class="muted">{{ \Illuminate\Support\Str::limit($item->body, 180) }}</p>
        <div class="meta-row"><small>{{ __('Audience') }}: {{ ucfirst($item->audience) }}</small><small>{{ $item->starts_at?->format('d M Y H:i') ?? __('Immediately') }} – {{ $item->ends_at?->format('d M Y H:i') ?? __('No end date') }}</small></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <a class="btn secondary" href="{{ route('admin.dashboard-announcements.edit', $item) }}">{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('admin.dashboard-announcements.toggle', $item) }}">@csrf @method('PATCH')<button class="btn secondary" type="submit">{{ $item->is_active ? __('Deactivate') : __('Activate') }}</button></form>
            <form method="POST" action="{{ route('admin.dashboard-announcements.destroy', $item) }}" onsubmit="return confirm('{{ __('Delete this announcement?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete') }}</button></form>
        </div>
    </article>
@empty
    <p class="muted">{{ __('No dashboard announcements created yet.') }}</p>
@endforelse
{{ $announcements->links() }}
</div>
@endsection
