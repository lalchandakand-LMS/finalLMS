@extends('layouts.app')
@section('title', __('System Updates'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('System administration') }}</span>
        <h1>{{ __('System Updates') }}</h1>
        <p class="muted">{{ __('Check for trusted, signed Alom’s English Center LMS releases.') }}</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a>
</div>

<div class="panel" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap;">
        <div>
            <h2>{{ __('Installed version') }}</h2>
            <p class="muted">{{ __('Current LMS version') }}</p>
            <strong style="font-size:1.7rem;">{{ $currentVersion }}</strong>
        </div>
        <form method="POST" action="{{ route('admin.updates.check') }}">
            @csrf
            <button class="btn primary" type="submit">{{ __('Check for updates') }}</button>
        </form>
    </div>
    @if(!$updatesEnabled)
        <div class="flash error" style="margin-top:16px;">
            {{ __('Secure release checking is currently disabled. Configure the trusted release public key outside the public web root and enable LMS_UPDATES_ENABLED after independently verifying its fingerprint.') }}
        </div>
    @endif
    <div class="flash info" style="margin-top:16px;">
        {{ __('After verification, you can extract the release into an isolated private staging directory. This does not activate or install code, run migrations or change the live site.') }}
    </div>
    @php($latestCheck = $checks->first())
    @if($updatesEnabled && $latestCheck && $latestCheck->status === 'available' && (!$preparation || $preparation->version !== $latestCheck->target_version || !in_array($preparation->status, ['verified', 'staged'], true) || !$preparation->expires_at || $preparation->expires_at->isPast()))
        <form method="POST" action="{{ route('admin.updates.prepare') }}" style="margin-top:16px;">
            @csrf
            <button class="btn secondary" type="submit">{{ __('Download and verify release package') }}</button>
        </form>
    @endif
    @if($preparation)
        <div class="course-item" style="margin-top:16px;">
            <strong>{{ __('Latest prepared package') }}: {{ $preparation->version }}</strong>
            <p class="muted">{{ $preparation->message }}</p>
            <small class="muted">
                {{ __('Preparation status') }}: {{ $preparation->expires_at?->isPast() ? __('Expired') : ucfirst($preparation->status) }}
                · {{ __('Size') }}: {{ number_format($preparation->file_size / 1024 / 1024, 2) }} MB
                · {{ __('Expires') }}: {{ $preparation->expires_at?->format('d M Y H:i') }}
            </small>
            <p><small class="muted">{{ __('Package SHA-256') }}: <code>{{ $preparation->package_sha256 }}</code></small></p>
            @if($preparation->status === 'verified' && $preparation->expires_at && $preparation->expires_at->isFuture())
                <form method="POST" action="{{ route('admin.updates.extract', $preparation) }}" style="margin-top:12px;">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('Extract to private staging') }}</button>
                </form>
            @elseif($preparation->status === 'staged' && $preparation->release_path && $preparation->expires_at && $preparation->expires_at->isFuture())
                <p><small class="muted">{{ __('Private staging directory') }}: <code>{{ $preparation->release_path }}</code></small></p>
                <p><small class="muted">{{ __('Extracted at') }}: {{ $preparation->staged_at?->format('d M Y H:i') }}</small></p>
                <div class="flash info">{{ __('This release is isolated in private staging only. It has not been activated or installed.') }}</div>
                <form method="POST" action="{{ route('admin.updates.preflight', $preparation) }}" style="margin-top:12px;">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('Run activation readiness preflight') }}</button>
                </form>
                @if($preflight && $preflight->preparation_id === $preparation->id)
                    <div class="panel" style="margin-top:16px;">
                        <strong>{{ __('Latest activation preflight') }}</strong>
                        <span class="badge {{ $preflight->status === 'blocked' ? 'error' : ($preflight->status === 'warning' ? 'warning' : 'success') }}">{{ ucfirst($preflight->status) }}</span>
                        <p class="muted">{{ $preflight->summary }}</p>
                        <small class="muted">{{ __('Checked') }}: {{ $preflight->checked_at?->format('d M Y H:i') }}</small>
                        @foreach(($preflight->checks ?? []) as $item)
                            <div class="course-item" style="margin-top:8px;">
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <strong>{{ str_replace('_', ' ', ucfirst($item['key'] ?? 'check')) }}</strong>
                                    <span class="badge {{ ($item['status'] ?? '') === 'blocked' ? 'error' : ((($item['status'] ?? '') === 'warning') ? 'warning' : 'success') }}">{{ ucfirst($item['status'] ?? 'unknown') }}</span>
                                </div>
                                <p class="muted">{{ $item['message'] ?? '' }}</p>
                                @if(!empty($item['details']['names']))
                                    <small class="muted">{{ __('Pending migrations requiring review') }}: <code>{{ implode(', ', $item['details']['names']) }}</code></small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>

<div class="panel">
    <h2>{{ __('Recent update checks') }}</h2>
    @forelse($checks as $check)
        <div class="course-item" style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
            <div style="min-width:240px;flex:1;">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <strong>
                        @if($check->status === 'available')
                            {{ __('New version available: :version', ['version' => $check->target_version]) }}
                        @elseif($check->status === 'up_to_date')
                            {{ __('Up to date') }}
                        @else
                            {{ __('Check failed') }}
                        @endif
                    </strong>
                    <span class="badge {{ $check->status === 'available' ? 'success' : ($check->status === 'failed' ? 'error' : 'info') }}">{{ str_replace('_', ' ', ucfirst($check->status)) }}</span>
                </div>
                <p class="muted">{{ $check->message }}</p>
                <small class="muted">
                    {{ __('Installed') }}: {{ $check->current_version }}
                    · {{ __('Checked') }}: {{ $check->checked_at?->format('d M Y H:i') }}
                    @if($check->checkedBy)
                        · {{ __('By') }} {{ $check->checkedBy->name }}
                    @endif
                </small>
                @if($check->git_commit)
                    <p><small class="muted">{{ __('Commit') }}: <code>{{ $check->git_commit }}</code></small></p>
                @endif
                @if($check->manifest_sha256)
                    <p><small class="muted">{{ __('Manifest SHA-256') }}: <code>{{ $check->manifest_sha256 }}</code></small></p>
                @endif
                @if($check->status === 'available' && $check->target_version)
                    <a href="https://github.com/lalchandakand-LMS/finalLMS/releases/tag/v{{ rawurlencode($check->target_version) }}" target="_blank" rel="noopener noreferrer">{{ __('View release notes') }}</a>
                @endif
            </div>
        </div>
    @empty
        <p class="muted">{{ __('No update checks have been performed yet.') }}</p>
    @endforelse
</div>
@endsection
