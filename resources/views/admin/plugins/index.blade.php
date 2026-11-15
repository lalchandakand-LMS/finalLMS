@extends('layouts.app')

@section('title', __('Plugin Manager') . ' | ' . config('app.name'))

@section('content')
@if($errors->any())
    <div class="flash error" role="alert">
        <strong>{{ __('Please correct the following:') }}</strong>
        <ul style="margin:8px 0 0;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Secure extension platform') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Plugin Manager') }}</h1>
        <p class="muted">{{ __('Install signed, independently packaged LMS plugins into the private plugins directory.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
</div>

<div class="panel" role="note" style="margin-bottom:20px;">
    <strong>{{ __('Installation and activation are separate') }}</strong>
    <p class="muted" style="margin-bottom:0;">{{ __('Packages are signature-verified and safely extracted. Inactive installed plugin PHP code is not loaded or executed. Activation checks file integrity, trusted release keys, compatibility, dependencies and conflicts. Active plugin code is loaded only through the verified runtime. Update and uninstall remain disabled until backup and rollback safeguards are complete.') }}</p>
</div>

<section class="panel" style="margin-bottom:24px;">
    <h2>{{ __('Install a signed plugin') }}</h2>
    <p class="muted">{{ __('Upload the ZIP, paste its exact signed manifest JSON, select the trusted release key ID and provide the detached Base64 signature.') }}</p>
    <form method="POST" action="{{ route('admin.plugins.install') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div>
                <label for="plugin-package">{{ __('Plugin ZIP package') }}</label>
                <input id="plugin-package" type="file" name="package" accept=".zip,application/zip" required>
                <small class="muted">{{ __('Maximum size:') }} {{ number_format($maxPackageBytes / 1048576, 0) }} MiB</small>
            </div>
            <div>
                <label for="plugin-key-id">{{ __('Trusted release key ID') }}</label>
                <input id="plugin-key-id" type="text" name="key_id" value="{{ old('key_id') }}" maxlength="120" required>
            </div>
            <div class="full-width">
                <label for="plugin-manifest">{{ __('Signed manifest JSON') }}</label>
                <textarea id="plugin-manifest" name="manifest" rows="12" spellcheck="false" required>{{ old('manifest') }}</textarea>
            </div>
            <div class="full-width">
                <label for="plugin-signature">{{ __('Detached signature (Base64)') }}</label>
                <textarea id="plugin-signature" name="signature" rows="3" spellcheck="false" required>{{ old('signature') }}</textarea>
            </div>
        </div>
        <button class="btn primary" type="submit">{{ __('Verify and install plugin') }}</button>
    </form>
</section>

<section class="panel">
    <h2>{{ __('Registered plugins') }}</h2>
    @if($plugins->isEmpty())
        <p class="muted">{{ __('No third-party plugins have been installed yet.') }}</p>
    @else
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Plugin') }}</th>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Signature') }}</th>
                        <th>{{ __('Installed') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($plugins as $plugin)
                    <tr>
                        <td>
                            <strong>{{ $plugin->name }}</strong>
                            <div class="muted"><code>{{ $plugin->plugin_key }}</code></div>
                        </td>
                        <td><code>{{ $plugin->version }}</code></td>
                        <td>{{ ucfirst($plugin->type) }}</td>
                        <td><span class="badge {{ in_array($plugin->status, ['installed', 'active', 'inactive'], true) ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $plugin->status)) }}</span></td>
                        <td>{{ $plugin->signature_verified_at ? __('Verified') : __('Not verified') }}</td>
                        <td>{{ $plugin->installed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                <form method="POST" action="{{ route('admin.plugins.activate', $plugin->plugin_key) }}">
                                    @csrf
                                    <button class="btn primary" type="submit">{{ __('Activate') }}</button>
                                </form>
                            @elseif($plugin->status === 'active')
                                <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin->plugin_key) }}">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Deactivate') }}</button>
                                </form>
                            @else
                                <span class="muted">{{ __('No action available') }}</span>
                            @endif
                            @if($plugin->health_message)
                                <small class="muted" style="display:block;margin-top:6px;">{{ $plugin->health_message }}</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
