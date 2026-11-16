@extends('layouts.app')

@section('title', __('Admin popup preview') . ' — ' . $popup->title)

@section('content')
<div class="page-header">
    <div>
        <span class="badge warning">{{ __('Admin-only preview') }}</span>
        <h1>{{ $popup->title }}</h1>
        <p class="muted">{{ __('Previewing layout, sanitized content and appearance. Public analytics are paused during admin preview.') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn primary" onclick="window.reopenPopupPreview && window.reopenPopupPreview()">{{ __('Show popup preview') }}</button>
        <a class="btn secondary" href="{{ route('admin.visitor-popups.edit', $popup->id) }}">{{ __('Edit campaign') }}</a>
        <a class="btn secondary" href="{{ route('admin.visitor-popups.index') }}">{{ __('Back') }}</a>
    </div>
</div>

<div class="panel">
    <div class="meta-row" style="flex-wrap:wrap;gap:12px;">
        <span><strong>{{ __('Status') }}:</strong> {{ __(ucfirst($popup->status ?: ($popup->is_active ? 'active' : 'draft'))) }}</span>
        <span><strong>{{ __('Layout') }}:</strong> {{ str_replace('_', ' ', ucfirst($popup->layout ?: 'modal')) }}</span>
        <span><strong>{{ __('Content mode') }}:</strong> {{ str_replace('_', ' ', ucfirst($popup->content_mode ?: 'mixed')) }}</span>
        <span><strong>{{ __('Audience') }}:</strong> {{ ucfirst($popup->audience) }}</span>
        <span><strong>{{ __('Timezone') }}:</strong> {{ $popup->timezone ?: 'UTC' }}</span>
    </div>
</div>

@include('popup-ads::visitor-popup', ['payload' => $payload, 'previewMode' => true])
@endsection
