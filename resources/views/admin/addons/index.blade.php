@extends('layouts.app')

@section('title', 'Add-on Registry | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">Admin control</span><h1 style="margin:12px 0 0;">Allow-listed Add-on Registry</h1><p class="muted">Manage reviewed integrations. Secrets are read from server environment/configuration and are never displayed or stored here.</p></div>
    <a class="btn secondary" href="{{ route('dashboard') }}">Back to dashboard</a>
</div>

<div class="panel" role="note" style="margin-bottom:20px;">
    <strong>Safety and status</strong>
    <p class="muted" style="margin-bottom:0;">Registry enablement is an allow-list switch, not a substitute for provider activation. Payment, storage and update adapters still require their own server credentials, provider connection and verification. No uploaded plugin code is executed. Health labels below are configuration checks, not live provider uptime probes.</p>
</div>

<div class="grid grid-2">
@foreach($addons as $key => $addon)
    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div><h2 style="margin:0 0 4px;">{{ $addon['name'] }}</h2><small class="muted">{{ $addon['provider'] }}</small></div>
            <span class="badge {{ $addon['effective'] ? 'success' : ($addon['blocked'] ? 'warning' : 'info') }}">{{ $addon['status'] }}</span>
        </div>
        <p>{{ $addon['purpose'] }}</p>
        <p><strong>Configuration / health:</strong> {{ $addon['health'] }}</p>
        <p><strong>Requirements:</strong> {{ $addon['requirements'] }}</p>
        <p><strong>Permission scopes:</strong></p>
        <ul>@foreach($addon['scopes'] as $scope)<li><code>{{ $scope }}</code></li>@endforeach</ul>
        <p><strong>Compatibility:</strong> PHP 8.3+ and Laravel 12 — {{ $addon['compatible'] ? 'compatible' : 'not compatible' }}</p>
        <form method="POST" action="{{ route('admin.addons.update', $key) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="enabled" value="{{ $addon['enabled'] ? '0' : '1' }}">
            <button class="btn {{ $addon['enabled'] ? 'secondary' : 'primary' }}" type="submit" @disabled(!$addon['enabled'] && (!$addon['config_ready'] || !$addon['compatible'] || $addon['blocked']))>
                {{ $addon['enabled'] ? 'Disable in registry' : 'Enable in registry' }}
            </button>
            <span class="muted">Registry: {{ $addon['enabled'] ? 'enabled' : 'disabled' }} · Effective: {{ $addon['effective'] ? 'available' : 'not active' }}</span>
        </form>
    </section>
@endforeach
</div>
@endsection
