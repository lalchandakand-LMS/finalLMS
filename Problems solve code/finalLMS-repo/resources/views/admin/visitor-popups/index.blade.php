@extends('layouts.app')
@section('title', __('Visitor popups'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Website marketing') }}</span><h1>{{ __('Visitor popups') }}</h1><p class="muted">{{ __('Create timed offers, banners, post promotions, ads or registration prompts for website visitors.') }}</p></div><a class="btn primary" href="{{ route('admin.visitor-popups.create') }}">{{ __('Create popup') }}</a></div>
<div class="panel"><div class="course-list">
@forelse($popups as $popup)
<div class="course-item">
    <div class="page-header" style="margin:0 0 10px;"><div><h3 style="margin:0 0 6px;">{{ $popup->title }}</h3><span class="badge info">{{ ucfirst($popup->popup_type) }}</span> <span class="badge {{ $popup->is_active ? 'success' : 'warning' }}">{{ $popup->is_active ? __('Active') : __('Inactive') }}</span></div><strong>{{ __('Priority') }}: {{ $popup->priority }}</strong></div>
    @if($popup->image)<img src="{{ $popup->image->url }}" alt="{{ $popup->title }}" loading="lazy" style="width:100%;max-width:360px;max-height:140px;object-fit:cover;border-radius:10px;">@endif
    <p class="muted">{{ \Illuminate\Support\Str::limit($popup->body, 180) }}</p>
    <div class="meta-row"><small>{{ __('Audience') }}: {{ ucfirst($popup->audience) }} · {{ __('Frequency') }}: {{ str_replace('_', ' ', $popup->frequency) }} · {{ __('Delay') }}: {{ $popup->delay_seconds }}s</small><small>{{ $popup->starts_at?->format('d M Y H:i') ?? __('Immediately') }} – {{ $popup->ends_at?->format('d M Y H:i') ?? __('No end date') }}</small></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
        <a class="btn secondary" href="{{ route('admin.visitor-popups.edit', $popup) }}">{{ __('Edit') }}</a>
        <form method="POST" action="{{ route('admin.visitor-popups.toggle', $popup) }}">@csrf @method('PATCH')<button class="btn secondary">{{ $popup->is_active ? __('Deactivate') : __('Activate') }}</button></form>
        <form method="POST" action="{{ route('admin.visitor-popups.destroy', $popup) }}" onsubmit="return confirm('{{ __('Delete this popup?') }}')">@csrf @method('DELETE')<button class="btn secondary">{{ __('Delete') }}</button></form>
    </div>
</div>
@empty<p class="muted">{{ __('No popup campaigns created yet.') }}</p>@endforelse
</div>{{ $popups->links() }}</div>
@endsection
