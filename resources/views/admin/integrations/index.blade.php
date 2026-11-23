@extends('layouts.app')

@section('title', __('API Integrations'))

@section('content')
<div class="container" style="max-width:1100px;">
    <div class="page-header"><div><span class="badge info">{{ __('Core integrations') }}</span><h1>{{ __('Official API Connector Framework') }}</h1><p class="muted">{{ __('Credentials are encrypted. A connector is not marked connected until its webhook is authenticated.') }}</p></div></div>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    @foreach($connectors as $key => $connector)
        <section class="panel" style="margin:16px 0;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div><h2 style="margin:0;">{{ $connector['label'] }}</h2><p class="muted">{{ __('Status') }}: <strong>{{ __(ucwords(str_replace('_',' ',$connector['status']))) }}</strong> · {{ $connector['webhook_verified'] ? __('Webhook verified') : __('Webhook not verified') }}</p></div>
                <span class="badge {{ $connector['status'] === 'connected' ? 'success' : 'warning' }}">{{ __(ucwords(str_replace('_',' ',$connector['status']))) }}</span>
            </div>
            <p class="muted">{{ __('Capabilities') }}: {{ collect($connector['capabilities'])->filter()->keys()->map(fn ($item) => str_replace('_',' ',$item))->implode(', ') }}</p>
            @if($connector['last_error'])<p class="muted">{{ __('Last error') }}: {{ $connector['last_error'] }}</p>@endif
            <form method="POST" action="{{ route('admin.integrations.connect', $key) }}" class="grid grid-2" style="margin-top:12px;">
                @csrf
                @foreach($connector['credential_schema'] as $field => $definition)
                    <label>{{ $definition['label'] }} @if($definition['required'])*@endif
                        <input name="{{ $field }}" type="{{ $definition['secret'] ? 'password' : 'text' }}" autocomplete="off" {{ $definition['required'] ? 'required' : '' }} maxlength="2000">
                    </label>
                @endforeach
                <div><button class="btn primary" type="submit">{{ __('Verify credentials') }}</button></div>
            </form>
            @if($connector['configured'])
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <form method="POST" action="{{ route('admin.integrations.health-check', $key) }}">@csrf<button class="btn secondary" type="submit">{{ __('Test connection') }}</button></form>
                    <form method="POST" action="{{ route('admin.integrations.pause', $key) }}">@csrf<button class="btn secondary" type="submit">{{ __('Pause') }}</button></form>
                    <form method="POST" action="{{ route('admin.integrations.disconnect', $key) }}" onsubmit="return confirm('{{ __('Disconnect this provider?') }}')">@csrf<button class="btn danger" type="submit">{{ __('Disconnect') }}</button></form>
                </div>
            @endif
            <small class="muted">{{ __('Webhook URL') }}: {{ url('/api/webhooks/api-connectors/'.$key) }}</small>
        </section>
    @endforeach
</div>
@endsection
