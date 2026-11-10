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
        {{ __('This screen currently verifies signed release metadata only. It does not download, install, migrate or replace application files. The in-app installer and rollback will be enabled only after security and cPanel staging acceptance.') }}
    </div>
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
