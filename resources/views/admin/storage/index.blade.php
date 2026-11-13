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
        <p class="muted">{{ __('Warning thresholds: :thresholds%', ['thresholds' => implode('%, ', $warningThresholds)]) }}%</p>
        <div class="flash">{{ __('Google Drive connection and verified file migration are a separate integration step. Google account storage is shared by Drive, Gmail and Photos; it is not connected by this quota panel yet.') }}</div>
    </section>
</div>
@endsection
