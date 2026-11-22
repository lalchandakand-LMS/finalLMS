@extends('layouts.app')

@section('title', __('Payment Gateways') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin control') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Universal Payment Gateways') }}</h1>
        <p class="muted">{{ __('Manage installed payment adapters. Provider credentials are encrypted at rest and are never displayed here.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
</div>
<div class="panel" role="note" style="margin-bottom:20px;">
    <strong>{{ __('Payment safety') }}</strong>
    <p class="muted" style="margin-bottom:0;">{{ __('Enabling a gateway only makes an installed and configured adapter available. It does not activate live merchant payments. Checkout callbacks must be independently verified by the provider API before a course or order is fulfilled.') }}</p>
</div>
<div class="panel" style="margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;">
    <a class="btn secondary" href="{{ route('admin.payment-reconciliation.index') }}">{{ __('Payment reconciliation') }}</a>
    <a class="btn secondary" href="{{ route('admin.payment-audit.index') }}">{{ __('Payment audit trail') }}</a>
</div>
<div class="grid grid-2">
@forelse($gateways as $key => $gateway)
    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div><h2 style="margin:0 0 4px;">{{ $gateway['name'] }}</h2><small class="muted">{{ strtoupper($key) }} · {{ __('Installed adapter') }}</small></div>
            <span class="badge {{ $gateway['available'] ? 'success' : 'info' }}">{{ $gateway['available'] ? __('Available') : __($gateway['status']) }}</span>
        </div>
        <p><strong>{{ __('Supported currencies:') }}</strong> {{ implode(', ', $gateway['currencies']) }}</p>
        <p><strong>{{ __('Server configuration:') }}</strong> {{ $gateway['configured'] ? __('Ready') : __('Missing or invalid') }}</p>
        <p><strong>{{ __('Registry:') }}</strong> {{ $gateway['enabled'] ? __('Enabled') : __('Disabled') }}</p>
        <form method="POST" action="{{ route('admin.payment-gateways.update', $key) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="enabled" value="{{ $gateway['enabled'] ? '0' : '1' }}">
            <button class="btn {{ $gateway['enabled'] ? 'secondary' : 'primary' }}" type="submit" @disabled(!$gateway['enabled'] && !$gateway['configured'])>
                {{ $gateway['enabled'] ? __('Disable gateway') : __('Enable gateway') }}
            </button>
        </form>
    </section>
@empty
    <section class="panel"><h2>{{ __('No payment adapters installed') }}</h2></section>
@endforelse
</div>
<section class="panel" style="margin-top:20px;">
    <h2>{{ __('Bangladesh MFS merchant configuration') }}</h2>
    <p class="muted">{{ __('Store sandbox merchant credentials for bKash, Nagad and Rocket. Values are encrypted at rest and never shown again after saving. This configuration foundation does not install or activate checkout adapters.') }}</p>
    <div class="grid grid-2">
        @foreach($mfsConfigurations as $provider => $configuration)
            <article class="panel" style="margin:0;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                    <h3 style="margin:0;">{{ $configuration['label'] }}</h3>
                    <span class="badge {{ $configuration['configured'] ? 'success' : 'info' }}">{{ $configuration['configured'] ? __('Credentials saved') : __('Not configured') }}</span>
                </div>
                @if($configuration['updated_at'])
                    <p class="muted">{{ __('Last updated:') }} {{ $configuration['updated_at']->format('Y-m-d H:i') }}</p>
                @endif
                <form method="POST" action="{{ route('admin.payment-gateways.mfs.update', $provider) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="mode" value="sandbox">
                    <label for="mfs-credentials-{{ $provider }}">{{ __('Merchant credentials (JSON)') }}</label>
                    <textarea id="mfs-credentials-{{ $provider }}" name="credentials_json" rows="5" required maxlength="12000" autocomplete="off" spellcheck="false"
                        placeholder="{{ $provider === 'bkash' ? '{&#10;  &quot;username&quot;: &quot;SANDBOX_USERNAME&quot;,&#10;  &quot;password&quot;: &quot;SANDBOX_PASSWORD&quot;,&#10;  &quot;app_key&quot;: &quot;SANDBOX_APP_KEY&quot;,&#10;  &quot;app_secret&quot;: &quot;SANDBOX_APP_SECRET&quot;&#10;}' : '{&#10;  &quot;credential_name&quot;: &quot;SANDBOX_VALUE&quot;&#10;}' }}"></textarea>
                    <p class="muted">{{ __('Submitting replaces the complete stored credential set. Secrets are never included in the page response or activity log.') }}</p>
                    <button class="btn primary" type="submit">{{ __('Save encrypted sandbox credentials') }}</button>
                </form>
            </article>
        @endforeach
    </div>
</section>
<section class="panel" style="margin-top:20px;">
    <h2>{{ __('Additional providers') }}</h2>
    <p class="muted">{{ __('Nagad and Rocket merchant configuration can be stored here, but checkout remains unavailable until their official API adapters, callback verification and sandbox acceptance tests are implemented. Live mode is intentionally disabled.') }}</p>
</section>
@endsection
