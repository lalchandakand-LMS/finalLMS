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
    <p class="muted" style="margin-bottom:0;">{{ __('Packages are signature-verified and safely extracted. Installed plugin PHP code is not loaded or executed. Verified historical rollback is available for versions with complete integrity evidence. Activation remains disabled until isolated runtime safeguards are complete.') }}</p>
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

<section class="panel" style="margin-bottom:24px;">
    <h2>{{ __('First-party LMS modules') }}</h2>
    <p class="muted">{{ __('Built-in business capabilities are catalogued here and retain their reviewed application code. Their enable/disable controls remain in Module Manager.') }}</p>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Module') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Dependencies') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($firstPartyModules as $module)
                    <tr>
                        <td>
                            <strong>{{ $module['name'] }}</strong>
                            <div class="muted"><code>{{ $module['key'] }}</code></div>
                        </td>
                        <td><span class="badge {{ $module['enabled'] ? 'success' : 'warning' }}">{{ $module['enabled'] ? __('Enabled') : __('Disabled') }}</span></td>
                        <td>{{ implode(', ', $module['dependencies']) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <a class="btn secondary" href="{{ route('admin.modules.index') }}">{{ __('Manage first-party modules') }}</a>
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
                        <td><span class="badge {{ $plugin->status === 'installed' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $plugin->status)) }}</span></td>
                        <td>{{ $plugin->signature_verified_at ? __('Verified') : __('Not verified') }}</td>
                        <td>{{ $plugin->installed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if(in_array($plugin->status, ['installed', 'active', 'inactive'], true))
                                <form method="POST" action="{{ route('admin.plugins.health-check', $plugin) }}" style="margin-bottom:8px;">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Run health check') }}</button>
                                </form>
                                <small class="muted">{{ __('Health:') }} {{ ucfirst($plugin->health_status) }}</small>
                            @endif
                            @if($plugin->status === 'active')
                                <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin) }}">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Deactivate') }}</button>
                                </form>
                            @elseif($plugin->status === 'inactive')
                                <form method="POST" action="{{ route('admin.plugins.uninstall', $plugin) }}">
                                    @csrf
                                    <label style="display:block;font-size:12px;margin-bottom:6px;">
                                        <input type="checkbox" name="confirm_uninstall" value="1" required>
                                        {{ __('Confirm backup and uninstall') }}
                                    </label>
                                    <button class="btn danger" type="submit">{{ __('Uninstall') }}</button>
                                </form>
                            @else
                                <span class="muted">{{ __('Activation is unavailable until the isolated runtime is complete') }}</span>
                            @endif
                            @if(is_array($plugin->metadata) && !empty($plugin->metadata['uninstall_backup_path']))
                                <small class="muted">{{ __('Private backup retained') }}</small>
                                @if($plugin->status === 'uninstalled')
                                    <form method="POST" action="{{ route('admin.plugins.restore', $plugin) }}" style="margin-top:8px;">
                                        @csrf
                                        <label style="display:block;font-size:12px;margin-bottom:6px;">
                                            <input type="checkbox" name="confirm_restore" value="1" required>
                                            {{ __('Confirm verified backup restore (plugin remains inactive)') }}
                                        </label>
                                        <button class="btn secondary" type="submit">{{ __('Verify and restore backup') }}</button>
                                    </form>
                                @endif
                            @endif
                            @if(!in_array($plugin->status, ['uninstalled', 'failed'], true))
                                <details style="margin-top:10px;">
                                    <summary>{{ __('Configure settings') }}</summary>
                                    <form method="POST" action="{{ route('admin.plugins.settings.update', $plugin) }}" style="margin-top:8px;">
                                        @csrf
                                        @method('PUT')
                                        <label>{{ __('Setting key') }}</label>
                                        <input type="text" name="setting_key" maxlength="120" pattern="[a-z][a-z0-9_.-]{0,119}" required>
                                        <label>{{ __('Value (encrypted at rest)') }}</label>
                                        <input type="password" name="value" maxlength="4096" autocomplete="new-password" required>
                                        <button class="btn secondary" type="submit">{{ __('Save securely') }}</button>
                                    </form>
                                    @php($configuredSettings = $settingsByPlugin->get($plugin->plugin_key, collect()))
                                    @if($configuredSettings->isNotEmpty())
                                        <small class="muted">{{ __('Saved keys (values hidden):') }}</small>
                                        <ul>
                                            @foreach($configuredSettings as $configuredSetting)
                                                <li><code>{{ $configuredSetting->setting_key }}</code> — {{ __('configured') }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </details>
                            @endif
                            @php($versionRecords = $versionHistoryByPlugin->get($plugin->plugin_key, collect()))
                            @if($versionRecords->isNotEmpty())
                                <details style="margin-top:10px;">
                                    <summary>{{ __('Version history') }}</summary>
                                    <ul>
                                        @foreach($versionRecords as $versionRecord)
                                            <li>
                                                <code>{{ $versionRecord->version }}</code>
                                                <small class="muted">{{ substr($versionRecord->package_sha256, 0, 12) }} · {{ $versionRecord->installed_at?->format('Y-m-d H:i') }}</small>
                                                @if($plugin->status === 'inactive' && $plugin->version !== $versionRecord->version && data_get($versionRecord->metadata, 'integrity_version') === 1 && !empty(data_get($versionRecord->metadata, 'installed_file_hashes')))
                                                    <form method="POST" action="{{ route('admin.plugins.rollback', $plugin) }}" style="display:inline-block; margin-left:8px;">
                                                        @csrf
                                                        <input type="hidden" name="version" value="{{ $versionRecord->version }}">
                                                        <label><input type="checkbox" name="confirm_rollback" value="1" required> {{ __('Confirm rollback') }}</label>
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('Rollback') }}</button>
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
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
