@extends('layouts.app')

@section('title', __('Module Manager') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Platform architecture') }}</span><h1 style="margin:12px 0 0;">{{ __('LMS Module Manager') }}</h1><p class="muted">{{ __('Manage first-party LMS modules and their dependencies.') }}</p></div>
    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
</div>
<div class="panel" role="note" style="margin-bottom:20px;">
    <strong>{{ __('Important safety note') }}</strong>
    <p class="muted" style="margin-bottom:0;">{{ __('This first-stage registry manages module lifecycle metadata and dependency rules. Existing routes are not all gated yet. Disabling does not uninstall code or delete data. Uploaded third-party PHP code is never executed.') }}</p>
</div>
<div class="grid grid-2">
@foreach($modules as $key => $module)
    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div><h2 style="margin:0 0 4px;">{{ $module['name'] }}</h2><small class="muted"><code>{{ $key }}</code> · {{ ucfirst($module['type']) }}</small></div>
            <span class="badge {{ $module['enabled'] ? 'success' : 'info' }}">{{ $module['status'] }}</span>
        </div>
        <p>{{ $module['description'] }}</p>
        <p><strong>{{ __('Dependencies:') }}</strong> {{ $module['dependencies'] ? implode(', ', $module['dependencies']) : __('None') }}</p>
        @if($module['managed_by_plugin_manager'] ?? false)
            <p class="muted">{{ $module['installed'] ? __('Installed package lifecycle:') : __('Not installed. Core LMS works normally without this add-on.') }} {{ str_replace('_', ' ', $module['status']) }}</p>
            <a class="btn secondary" href="{{ route('admin.plugins.index') }}">{{ __('Manage signed plugin') }}</a>
        @else
            <form method="POST" action="{{ route('admin.modules.update', $key) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $module['enabled'] ? '0' : '1' }}">
                <button class="btn {{ $module['enabled'] ? 'secondary' : 'primary' }}" type="submit" @disabled($module['protected'])>
                    {{ $module['protected'] ? __('Protected core module') : ($module['enabled'] ? __('Disable module') : __('Enable module')) }}
                </button>
            </form>
        @endif
    </section>
@endforeach
</div>
@endsection
