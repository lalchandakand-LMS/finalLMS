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

                            @if($plugin->versionHistory->isNotEmpty())
                                <details style="margin-top:8px;">
                                    <summary>{{ __('Version history') }}</summary>
                                    <ol style="margin:8px 0 0;padding-left:20px;">
                                        @foreach($plugin->versionHistory as $history)
                                            <li style="margin:4px 0;">
                                                <strong>{{ ucfirst(str_replace('_', ' ', $history->action)) }}</strong>
                                                <span class="muted">v{{ $history->version }} · {{ $history->created_at?->format('Y-m-d H:i') }}</span>
                                                @if($history->actor)
                                                    <span class="muted">· {{ $history->actor->name }}</span>
                                                @endif
                                                @if($history->action === 'version_snapshot' && in_array($plugin->status, ['installed', 'inactive', 'failed'], true) && (string) ($history->details['snapshot']['version'] ?? '') !== (string) $plugin->version)
                                                    <form method="POST" action="{{ route('admin.plugins.restore', [$plugin->plugin_key, $history->id]) }}" style="display:inline;margin-top:5px;" onsubmit="return confirm('{{ __('Restore this verified version? The current version migrations will be rolled back first. Back up the database before continuing.') }}')">
                                                        @csrf
                                                        <input type="hidden" name="confirm_restore" value="1">
                                                        <button class="btn danger" type="submit">{{ __('Restore this version') }} · v{{ $history->details['snapshot']['version'] }}</button>
                                                    </form>
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
                            @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                <details style="margin-bottom:8px;">
                                    <summary>{{ __('Install newer version') }}</summary>
                                    <form method="POST" action="{{ route('admin.plugins.update', $plugin->plugin_key) }}" enctype="multipart/form-data" style="margin-top:8px;">
                                        @csrf
                                        <label>{{ __('New signed ZIP package') }}<input type="file" name="package" accept=".zip,application/zip" required></label>
                                        <label>{{ __('New version manifest JSON') }}<textarea name="manifest" rows="7" required spellcheck="false"></textarea></label>
                                        <label>{{ __('Detached Base64 signature') }}<textarea name="signature" rows="2" required spellcheck="false"></textarea></label>
                                        <label>{{ __('Trusted key ID') }}<input type="text" name="key_id" maxlength="120" required></label>
                                        <button class="btn primary" type="submit">{{ __('Verify and install update') }}</button>
                                    </form>
                                </details>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive', 'active', 'failed'], true))
                                <form method="POST" action="{{ route('admin.plugins.health-check', $plugin->plugin_key) }}" style="display:inline;">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Run health check') }}</button>
                                </form>
                            @endif
                            @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                <form method="POST" action="{{ route('admin.plugins.rollback-migrations', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Roll back every completed migration for this plugin version? This can change or remove plugin data. The plugin must remain inactive. Back up the database first.') }}')">
                                    @csrf
                                    <input type="hidden" name="confirm_rollback" value="1">
                                    <button class="btn danger" type="submit">{{ __('Rollback migrations') }}</button>
                                </form>
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
                            @if(in_array($plugin->status, ['installed', 'inactive', 'failed'], true))
                                <form method="POST" action="{{ route('admin.plugins.uninstall', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Uninstall this plugin package? Conversation history, LMS data and migration records will be retained.') }}')">
                                    @csrf
                                    <button class="btn danger" type="submit">{{ __('Uninstall') }}</button>
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
