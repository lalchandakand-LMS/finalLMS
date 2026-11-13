@extends('layouts.app')
@section('title', __('Storage Manager'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('System administration') }}</span>
        <h1>{{ __('Storage Manager') }}</h1>
        <p class="muted">{{ __('View the hosting account storage quota reported by cPanel. No passwords or API tokens are entered or displayed here.') }}</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a>
</div>

<div class="grid grid-2">
    <section class="panel">
        <h2>{{ __('cPanel account-wide storage') }}</h2>
        @if($cpanelQuota['available'])
            <div class="metric">{{ number_format($cpanelQuota['used_bytes'] / 1073741824, 2) }} GB {{ __('used') }}</div>
            @if(!empty($cpanelQuota['unlimited']))
                <p class="muted">{{ __('The hosting account reports an unlimited disk quota.') }}</p>
            @else
                <p class="muted">
                    {{ number_format(($cpanelQuota['limit_bytes'] ?? 0) / 1073741824, 2) }} GB {{ __('total') }}
                    · {{ number_format(($cpanelQuota['free_bytes'] ?? 0) / 1073741824, 2) }} GB {{ __('remaining') }}
                </p>
                <div class="progress" role="progressbar" aria-label="{{ __('Storage usage') }}" aria-valuenow="{{ $cpanelQuota['usage_percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $cpanelQuota['usage_percent'] ?? 0 }}%"></span>
                </div>
                <p>{{ $cpanelQuota['usage_percent'] ?? 0 }}% {{ __('of the cPanel account quota is used.') }}</p>
                @if(($cpanelQuota['usage_percent'] ?? 0) >= 95)
                    <div class="flash error">{{ __('Critical: account storage usage is at or above 95%.') }}</div>
                @elseif(($cpanelQuota['usage_percent'] ?? 0) >= 85)
                    <div class="flash error">{{ __('High storage usage: account usage is at or above 85%.') }}</div>
                @elseif(($cpanelQuota['usage_percent'] ?? 0) >= 70)
                    <div class="flash">{{ __('Storage usage has reached the 70% warning threshold.') }}</div>
                @endif
            @endif
            <p class="muted">{{ __('Source: cPanel UAPI') }} · {{ $cpanelQuota['checked_at'] }}</p>
        @else
            <div class="flash">{{ __('Live cPanel quota is not connected yet.') }}</div>
            <p>{{ $cpanelQuota['reason'] ?? __('Quota information is unavailable.') }}</p>
            <p class="muted">{{ __('In cPanel, create an API token under Security → Manage API Tokens. Add CPANEL_API_HOST, CPANEL_API_USERNAME and CPANEL_API_TOKEN to the server environment. Use an API token, never the cPanel password.') }}</p>
        @endif
    </section>

    <section class="panel">
        <h2>{{ __('LMS storage target') }}</h2>
        <div class="metric">{{ number_format($configuredLocalQuotaBytes / 1073741824, 2) }} GB</div>
        <p class="muted">{{ __('Configured planning quota for LMS-managed files. This is not a live measurement of account-wide hosting usage.') }}</p>
        <p class="muted">{{ __('Warning thresholds: :thresholds%', ['thresholds' => implode(', ', $warningThresholds)]) }}%</p>
        <div class="flash">{{ __('Google Drive connection and verified file migration are a separate integration step. Google account storage is shared by Drive, Gmail and Photos; it is not connected by this quota panel yet.') }}</div>
    </section>
</div>

<div class="panel" style="margin-top:20px;">
    <h2>{{ __('Google account storage (Drive, Gmail and Photos)') }}</h2>
    @if($driveQuota['connected'] && $driveQuota['available'])
        <p class="muted">{{ $driveQuota['email'] }} · {{ __('Google account storage is shared across Drive, Gmail and Photos.') }}</p>
        <div class="metric">{{ number_format($driveQuota['used_bytes'] / 1073741824, 2) }} GB {{ __('used') }}</div>
        @if(!empty($driveQuota['unlimited']))
            <p class="muted">{{ __('Google reports no fixed storage limit for this account.') }}</p>
        @else
            <p class="muted">{{ number_format(($driveQuota['limit_bytes'] ?? 0) / 1073741824, 2) }} GB {{ __('total') }} · {{ number_format(($driveQuota['free_bytes'] ?? 0) / 1073741824, 2) }} GB {{ __('remaining') }}</p>
            <div class="progress" role="progressbar" aria-label="{{ __('Google account storage usage') }}" aria-valuenow="{{ $driveQuota['usage_percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                <span style="width: {{ $driveQuota['usage_percent'] ?? 0 }}%"></span>
            </div>
            <p>{{ $driveQuota['usage_percent'] ?? 0 }}% {{ __('of Google account storage is used.') }}</p>
        @endif
        <p class="muted">{{ __('Quota source: Google Drive API') }} · {{ $driveQuota['checked_at'] }}</p>
    @elseif($driveQuota['connected'])
        <div class="flash">{{ $driveQuota['reason'] ?? __('Google storage information is unavailable.') }}</div>
        <p class="muted">{{ $driveQuota['email'] ?? '' }}</p>
    @else
        <p class="muted">{{ __('Connect a dedicated Google account to view its shared Drive/Gmail/Photos storage quota. The LMS requests limited per-file Drive access, not full access to every personal file.') }}</p>
    @endif

    @if($driveQuota['connected'])
        <form method="POST" action="{{ route('admin.storage.google.disconnect') }}" onsubmit="return confirm('{{ __('Disconnect this Google account? Existing Drive files will not be deleted.') }}')">
            @csrf
            <button class="btn secondary" type="submit">{{ __('Disconnect Google account') }}</button>
        </form>
    @elseif($googleDriveConfigured)
        <a class="btn primary" href="{{ route('admin.storage.google.connect') }}">{{ __('Connect Google Drive') }}</a>
    @else
        <div class="flash">{{ __('Google OAuth is not configured. Add GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET and GOOGLE_DRIVE_REDIRECT_URI to the server environment first.') }}</div>
    @endif
</div>
@endsection
