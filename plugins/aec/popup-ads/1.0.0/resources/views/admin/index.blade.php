@extends('layouts.app')

@section('title', __('Visitor popups'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Website marketing') }}</span>
        <h1>{{ __('Visitor popups') }}</h1>
        <p class="muted">{{ __('Create timed offers, banners, announcements, post promotions, ads or registration prompts with targeting and consent-aware analytics.') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn secondary" href="{{ route('admin.visitor-popups.export') }}">{{ __('Export JSON') }}</a>
        <a class="btn primary" href="{{ route('admin.visitor-popups.create') }}">{{ __('Create popup') }}</a>
    </div>
</div>

<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
    <div class="panel" style="margin:0;">
        <small class="muted">{{ __('Total campaigns') }}</small>
        <div style="font-size:1.6rem;font-weight:800;margin-top:4px;">{{ $summary['total'] }}</div>
    </div>
    <div class="panel" style="margin:0;">
        <small class="muted">{{ __('Active / Scheduled') }}</small>
        <div style="font-size:1.6rem;font-weight:800;margin-top:4px;">{{ $summary['active'] }}</div>
    </div>
    <div class="panel" style="margin:0;">
        <small class="muted">{{ __('Total impressions') }}</small>
        <div style="font-size:1.6rem;font-weight:800;margin-top:4px;">{{ number_format($summary['impressions']) }}</div>
    </div>
    <div class="panel" style="margin:0;">
        <small class="muted">{{ __('Total clicks') }}</small>
        <div style="font-size:1.6rem;font-weight:800;margin-top:4px;">{{ number_format($summary['clicks']) }}</div>
    </div>
</div>

<div class="panel" style="margin-bottom:20px;">
    <form method="GET" action="{{ route('admin.visitor-popups.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            @foreach(['all' => 'All', 'active' => 'Active', 'scheduled' => 'Scheduled', 'paused' => 'Paused', 'draft' => 'Draft', 'archived' => 'Archived'] as $key => $label)
                <a class="btn {{ $statusFilter === $key ? 'primary' : 'secondary' }}" href="{{ route('admin.visitor-popups.index', ['status' => $key, 'q' => $search ?: null]) }}">{{ __($label) }}</a>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search campaigns...') }}" style="min-width:220px;">
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <button class="btn secondary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="course-list">
        @forelse($popups as $popup)
            @php
                $impressions = (int) ($popup->impressions_count ?? 0);
                $clicks = (int) ($popup->clicks_count ?? 0);
                $dismissals = (int) ($popup->dismissals_count ?? 0);
                $ctr = $impressions > 0 ? number_format(($clicks / $impressions) * 100, 1) : '0.0';
                $statusKey = $popup->status ?: ($popup->is_active ? 'active' : 'draft');
                $statusBadge = match ($statusKey) {
                    'active' => 'success',
                    'scheduled' => 'info',
                    'archived' => 'secondary',
                    default => 'warning',
                };
            @endphp
            <div class="course-item">
                <div class="page-header" style="margin:0 0 10px;">
                    <div>
                        <h3 style="margin:0 0 6px;">{{ $popup->title }}</h3>
                        <span class="badge info">{{ ucfirst($popup->popup_type) }}</span>
                        <span class="badge {{ $statusBadge }}">{{ __(ucfirst($statusKey)) }}</span>
                        <span class="badge secondary">{{ str_replace('_', ' ', ucfirst($popup->layout ?: 'modal')) }}</span>
                    </div>
                    <strong>{{ __('Priority') }}: {{ $popup->priority }}</strong>
                </div>

                @if($popup->image)
                    <img src="{{ $popup->image->url }}" alt="{{ $popup->title }}" loading="lazy" style="width:100%;max-width:360px;max-height:140px;object-fit:cover;border-radius:10px;">
                @endif

                <p class="muted">{{ \Illuminate\Support\Str::limit($popup->body ?: strip_tags((string) $popup->rich_html), 180) }}</p>

                <div class="meta-row" style="flex-wrap:wrap;gap:10px;">
                    <small>
                        {{ __('Audience') }}: {{ ucfirst($popup->audience) }}
                        · {{ __('Frequency') }}: {{ str_replace('_', ' ', $popup->frequency) }}
                        · {{ __('Delay') }}: {{ $popup->delay_seconds }}s
                        @if($popup->scroll_trigger_percent > 0) · {{ __('Scroll') }}: {{ $popup->scroll_trigger_percent }}% @endif
                        @if($popup->max_impressions) · {{ __('Max impressions') }}: {{ number_format($popup->max_impressions) }} @endif
                    </small>
                    <small>
                        {{ $popup->starts_at?->copy()->timezone($popup->timezone ?: 'UTC')->format('d M Y H:i') ?? __('Immediately') }}
                        –
                        {{ $popup->ends_at?->copy()->timezone($popup->timezone ?: 'UTC')->format('d M Y H:i') ?? __('No end date') }}
                        ({{ $popup->timezone ?: 'UTC' }})
                    </small>
                </div>

                <div class="meta-row" style="margin-top:8px;">
                    <small>
                        {{ __('Impressions') }}: <strong>{{ number_format($impressions) }}</strong>
                        · {{ __('Clicks') }}: <strong>{{ number_format($clicks) }}</strong>
                        · {{ __('Dismissals') }}: <strong>{{ number_format($dismissals) }}</strong>
                        · {{ __('CTR') }}: <strong>{{ $ctr }}%</strong>
                    </small>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
                    <a class="btn secondary" href="{{ route('admin.visitor-popups.preview', $popup->id) }}">{{ __('Preview') }}</a>
                    <a class="btn secondary" href="{{ route('admin.visitor-popups.edit', $popup->id) }}">{{ __('Edit') }}</a>
                    <form method="POST" action="{{ route('admin.visitor-popups.duplicate', $popup->id) }}">
                        @csrf
                        <button class="btn secondary" type="submit">{{ __('Duplicate') }}</button>
                    </form>
                    @if($popup->is_active)
                        <form method="POST" action="{{ route('admin.visitor-popups.pause', $popup->id) }}">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Pause') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.visitor-popups.resume', $popup->id) }}">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Resume') }}</button>
                        </form>
                    @endif
                    @if($statusKey !== 'archived')
                        <form method="POST" action="{{ route('admin.visitor-popups.archive', $popup->id) }}">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Archive') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.visitor-popups.destroy', $popup->id) }}" onsubmit="return confirm('{{ __('Delete this popup?') }}')">
                        @csrf @method('DELETE')
                        <button class="btn secondary" type="submit">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">{{ __('No popup campaigns created yet.') }}</p>
        @endforelse
    </div>

    {{ $popups->links() }}
</div>

<section class="panel" style="margin-top:24px;">
    <h2>{{ __('Data retention, export & destructive purge') }}</h2>
    <p class="muted">{{ __('Deactivating or uninstalling Popup Ads retains all campaign configurations and aggregate daily analytics. Export a backup before performing a permanent purge.') }}</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <a class="btn secondary" href="{{ route('admin.visitor-popups.export') }}">{{ __('Export campaigns & analytics (JSON)') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.visitor-popups.purge') }}" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);" onsubmit="return confirm('{{ __('Permanently delete all popup campaigns and analytics?') }}')">
        @csrf
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <input type="checkbox" name="confirm_purge" value="1" style="width:auto;" required>
            <span>{{ __('I understand this permanently deletes all stored popup campaigns and event metrics.') }}</span>
        </label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="confirmation_phrase" placeholder="ERASE POPUP DATA" required style="max-width:260px;">
            <button class="btn danger" type="submit">{{ __('Erase all popup data') }}</button>
        </div>
    </form>
</section>
@endsection
