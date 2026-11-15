@extends('layouts.app')

@section('title', __('Payment Gateways') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin control') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Universal Payment Gateways') }}</h1>
        <p class="muted">{{ __('Manage installed payment adapters. Provider credentials are kept in hosting environment secrets and are never displayed here.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
</div>

<div class="panel" role="note" style="margin-bottom:20px;">
    <strong>{{ __('Payment safety') }}</strong>
    <p class="muted" style="margin-bottom:0;">{{ __('Enabling a gateway only makes an installed and configured adapter available. It does not activate live merchant payments. Checkout callbacks must be independently verified by the provider API before a course or order is fulfilled.') }}</p>
</div>

<div class="grid grid-2">
@forelse($gateways as $key => $gateway)
    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div>
                <h2 style="margin:0 0 4px;">{{ $gateway['name'] }}</h2>
                <small class="muted">{{ strtoupper($key) }} · {{ __('Installed adapter') }}</small>
            </div>
            <span class="badge {{ $gateway['available'] ? 'success' : 'info' }}">
                {{ $gateway['available'] ? __('Available') : __($gateway['status']) }}
            </span>
        </div>
        <p><strong>{{ __('Supported currencies:') }}</strong> {{ implode(', ', $gateway['currencies']) }}</p>
        <p><strong>{{ __('Server configuration:') }}</strong> {{ $gateway['configured'] ? __('Ready') : __('Missing or invalid') }}</p>
        <p><strong>{{ __('Registry:') }}</strong> {{ $gateway['enabled'] ? __('Enabled') : __('Disabled') }}</p>
        <form method="POST" action="{{ route('admin.payment-gateways.update', $key) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="enabled" value="{{ $gateway['enabled'] ? '0' : '1' }}">
            <button class="btn {{ $gateway['enabled'] ? 'secondary' : 'primary' }}" type="submit"
                @disabled(!$gateway['enabled'] && !$gateway['configured'])>
                {{ $gateway['enabled'] ? __('Disable gateway') : __('Enable gateway') }}
            </button>
        </form>
    </section>
@empty
    <section class="panel">
        <h2>{{ __('No payment adapters installed') }}</h2>
    </section>
@endforelse
</div>

<section class="panel" style="margin-top:20px;">
    <h2>{{ __('Additional providers') }}</h2>
    <p class="muted">{{ __('Nagad, SSLCommerz, Stripe and PayPal are not installed as payment adapters yet. They will appear here only after their official merchant API integration, security checks and sandbox tests are completed.') }}</p>
</section>
@endsection
