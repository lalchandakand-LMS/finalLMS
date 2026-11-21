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
    <p class="muted" style="margin-bottom:0;">{{ __('Only trusted, signed packages with intact installed-file hashes can be activated. Activation registers verified plugin routes on the next request. Deactivation blocks plugin routes immediately and retains installed files and data. Clear route cache before activation.') }}</p>
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
                        <th>{{ __('Health') }}</th>
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
                            @if(!empty($plugin->manifest['permissions']))
                                <details style="margin-top:8px;">
                                    <summary>{{ __('Permission grants') }}</summary>
                                    <form method="POST" action="{{ route('admin.plugins.permissions', $plugin->plugin_key) }}" style="margin-top:8px;">
                                        @csrf
                                        @method('PUT')
                                        @foreach($plugin->manifest['permissions'] as $permission)
                                            <label style="display:block;margin:5px 0;">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $plugin->metadata['granted_permissions'] ?? [], true))>
                                                <code>{{ $permission }}</code>
                                            </label>
                                        @endforeach
                                        <button class="btn secondary" type="submit">{{ __('Save grants') }}</button>
                                    </form>
                                </details>
                            @endif

                            @php($settingsForPlugin = $pluginSettings[$plugin->plugin_key] ?? [])
                            @if(in_array($plugin->status, ['installed', 'active', 'inactive'], true))
                                <details style="margin-top:8px;">
                                    <summary>{{ __('Encrypted plugin settings') }} ({{ count($settingsForPlugin) }})</summary>
                                    @php($settingsSchema = $plugin->manifest['settings_schema'] ?? [])
                                    @if(is_array($settingsSchema) && $settingsSchema !== [])
                                        @php($configuredSettingKeys = array_column($settingsForPlugin, 'key'))
                                        @foreach($settingsSchema as $settingKey => $definition)
                                            <form method="POST" action="{{ route('admin.plugins.settings.store', $plugin->plugin_key) }}" style="margin-top:10px;display:grid;gap:6px;max-width:420px;">
                                                @csrf
                                                <input type="hidden" name="setting_key" value="{{ $settingKey }}">
                                                <label>
                                                    {{ $definition['label'] ?? $settingKey }}
                                                    @if($definition['required'] ?? false)<span aria-label="{{ __('required') }}">*</span>@endif
                                                    @if(($definition['type'] ?? 'text') === 'select')
                                                        <select name="setting_value" @required($definition['required'] ?? false)>
                                                            @unless($definition['required'] ?? false)<option value="">{{ __('Not configured / clear') }}</option>@endunless
                                                            @foreach($definition['options'] ?? [] as $option)
                                                                <option value="{{ $option }}" @selected(($definition['default'] ?? null) === $option)>{{ $option }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif(($definition['type'] ?? 'text') === 'boolean')
                                                        <select name="setting_value" @required($definition['required'] ?? false)>
                                                            <option value="">{{ __('Not configured / clear') }}</option>
                                                            <option value="1" @selected(($definition['default'] ?? null) === true || ($definition['default'] ?? null) === 1)>{{ __('Enabled') }}</option>
                                                            <option value="0" @selected(($definition['default'] ?? null) === false || ($definition['default'] ?? null) === 0)>{{ __('Disabled') }}</option>
                                                        </select>
                                                    @else
                                                        <input
                                                            type="{{ match($definition['type'] ?? 'text') { 'secret' => 'password', 'url' => 'url', 'email' => 'email', 'integer' => 'number', default => 'text' } }}"
                                                            name="setting_value"
                                                            maxlength="4096"
                                                            autocomplete="new-password"
                                                            placeholder="{{ in_array($settingKey, $configuredSettingKeys, true) ? __('Configured — enter a new value to replace') : ($definition['default'] ?? '') }}"
                                                            @if(($definition['type'] ?? null) === 'integer') step="1" @endif
                                                            @required($definition['required'] ?? false)
                                                        >
                                                    @endif
                                                </label>
                                                @if(in_array($settingKey, $configuredSettingKeys, true))
                                                    <small class="muted">{{ __('A value is configured. Existing values are never displayed.') }}</small>
                                                @endif
                                                <button class="btn secondary" type="submit">{{ __('Save encrypted setting') }}</button>
                                            </form>
                                        @endforeach
                                    @else
                                        <form method="POST" action="{{ route('admin.plugins.settings.store', $plugin->plugin_key) }}" style="margin-top:8px;display:grid;gap:8px;max-width:420px;">
                                            @csrf
                                            <label>{{ __('Setting key') }}<input name="setting_key" maxlength="120" pattern="[a-z][a-z0-9_.-]{0,119}" placeholder="api_key" required></label>
                                            <label>{{ __('Secret / value') }}<input type="password" name="setting_value" maxlength="4096" autocomplete="new-password" required></label>
                                            <button class="btn secondary" type="submit">{{ __('Save encrypted setting') }}</button>
                                        </form>
                                    @endif
                                    @forelse($settingsForPlugin as $storedSetting)
                                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                                            <code>{{ $storedSetting['key'] }}</code>
                                            <span class="muted">{{ $storedSetting['updated_at']?->format('Y-m-d H:i') }}</span>
                                            <form method="POST" action="{{ route('admin.plugins.settings.delete', [$plugin->plugin_key, $storedSetting['key']]) }}" onsubmit="return confirm('{{ __('Delete this plugin setting?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn danger" type="submit">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    @empty
                                        <p class="muted">{{ __('No plugin settings saved.') }}</p>
                                    @endforelse
                                </details>
                            @endif

                            @if($plugin->versionHistory->isNotEmpty())
                                <details style="margin-top:8px;">
                                    <summary>{{ __('Version history') }}</summary>
                                    <ol style="margin:8px 0 0;padding-left:20px;">
                                        @foreach($plugin->versionHistory->take(10) as $history)
                                            <li style="margin:4px 0;">
                                                <strong>{{ ucfirst(str_replace('_', ' ', $history->action)) }}</strong>
                                                <span class="muted">v{{ $history->version }} · {{ $history->created_at?->format('Y-m-d H:i') }}</span>
                                                @if($history->actor)
                                                    <span class="muted">· {{ $history->actor->name }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif
                        </td>
                        <td><code>{{ $plugin->version }}</code></td>
                        <td>{{ ucfirst($plugin->type) }}</td>
                        <td><span class="badge {{ $plugin->status === 'installed' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $plugin->status)) }}</span></td>
                        <td>{{ $plugin->signature_verified_at ? __('Verified') : __('Not verified') }}</td>
                        <td>
                            <span class="badge {{ $plugin->health_status === 'healthy' ? 'success' : ($plugin->health_status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($plugin->health_status) }}</span>
                            <div class="muted">{{ $plugin->last_health_check_at?->format('Y-m-d H:i') ?? __('Not checked') }}</div>
                        </td>
                        <td>{{ $plugin->installed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if(in_array($plugin->status, ['installed', 'inactive', 'active', 'failed'], true))
                                <form method="POST" action="{{ route('admin.plugins.health-check', $plugin->plugin_key) }}" style="display:inline;">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Run health check') }}</button>
                                </form>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                <form method="POST" action="{{ route('admin.plugins.activate', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Activate this signed plugin? Its code will execute as trusted application code.') }}')">
                                    @csrf
                                    <button class="btn primary" type="submit">{{ __('Activate') }}</button>
                                </form>
                            @elseif($plugin->status === 'active')
                                @if($plugin->plugin_key === 'aec.popup-ads' && \Illuminate\Support\Facades\Route::has('admin.visitor-popups.index'))
                                    <a class="btn secondary" href="{{ route('admin.visitor-popups.index') }}">{{ __('Configure') }}</a>
                                @endif
                                <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Deactivate this plugin? Its data and installed files will be retained.') }}')">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Deactivate') }}</button>
                                </form>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                <details style="margin-top:8px;">
                                    <summary>{{ __('Update signed package') }}</summary>
                                    <p class="muted">{{ __('Only a newer signed version can be installed. Deactivate first; the previous package and its version metadata are retained.') }}</p>
                                    <form method="POST" action="{{ route('admin.plugins.update-package', $plugin->plugin_key) }}" enctype="multipart/form-data" style="display:grid;gap:8px;max-width:520px;" onsubmit="return confirm('{{ __('Update this plugin? The plugin must be inactive. Review the signed release and take a database backup first.') }}')">
                                        @csrf
                                        <input type="hidden" name="confirm_update" value="1">
                                        <label>{{ __('New plugin ZIP') }}
                                            <input type="file" name="package" accept=".zip,application/zip" required>
                                        </label>
                                        <label>{{ __('Trusted release key ID') }}
                                            <input type="text" name="key_id" maxlength="120" value="{{ $plugin->signature_key_id }}" required>
                                        </label>
                                        <label>{{ __('New signed manifest JSON') }}
                                            <textarea name="manifest" rows="8" spellcheck="false" required>{{ json_encode($plugin->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</textarea>
                                        </label>
                                        <label>{{ __('New detached signature (Base64)') }}
                                            <textarea name="signature" rows="3" maxlength="512" spellcheck="false" required></textarea>
                                        </label>
                                        <button class="btn primary" type="submit">{{ __('Verify and update package') }}</button>
                                    </form>
                                </details>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive'], true) && $plugin->versionHistory->contains(fn ($history) => $history->action === 'package_update_snapshot' && $history->version !== $plugin->version))
                                <form method="POST" action="{{ route('admin.plugins.rollback-package', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Rollback to the retained previous package? This reverses the current version migrations. Create a database backup first.') }}')">
                                    @csrf
                                    <input type="hidden" name="confirm_rollback" value="1">
                                    <button class="btn secondary" type="submit">{{ __('Rollback package version') }}</button>
                                </form>
                            @endif
                            @if($plugin->status === 'uninstalled' && $plugin->versionHistory->contains(fn ($history) => $history->action === 'pre_uninstall_snapshot' && $history->version === $plugin->version))
                                <form method="POST" action="{{ route('admin.plugins.restore-package', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Restore this exact signed plugin package from its verified pre-uninstall snapshot? It will remain inactive.') }}')">
                                    @csrf
                                    <input type="hidden" name="confirm_restore" value="1">
                                    <button class="btn secondary" type="submit">{{ __('Restore package') }}</button>
                                </form>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive', 'failed', 'uninstalled'], true))
                                <form method="POST" action="{{ route('admin.plugins.uninstall', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ $plugin->status === 'uninstalled' ? __('Retry removal of remaining plugin package files? LMS data and migration records will be retained.') : __('Uninstall this plugin package? LMS data and migration records will be retained.') }}')">
                                    @csrf
                                    <input type="hidden" name="confirm_uninstall" value="1">
                                    <button class="btn danger" type="submit">{{ $plugin->status === 'uninstalled' ? __('Retry package cleanup') : __('Uninstall') }}</button>
                                </form>
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
