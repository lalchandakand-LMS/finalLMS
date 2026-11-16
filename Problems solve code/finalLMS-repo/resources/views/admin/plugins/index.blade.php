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
        <p class="muted" style="margin:6px 0 0;">
            <small>
                {{ __('Runtime compatibility baseline:') }}
                <code>PHP {{ $environmentVersions['php'] }}</code> ·
                <code>Laravel {{ $environmentVersions['laravel'] }}</code> ·
                <code>LMS {{ $environmentVersions['lms'] }}</code>
            </small>
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <form method="POST" action="{{ route('admin.plugins.discover') }}" style="display:inline;">
            @csrf
            <button class="btn secondary" type="submit">{{ __('Scan plugins directory') }}</button>
        </form>
        <a class="btn secondary" href="{{ route('admin.modules.index') }}">{{ __('Module Manager') }}</a>
        <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
    </div>
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
                <textarea id="plugin-manifest" name="manifest" rows="10" spellcheck="false" required>{{ old('manifest') }}</textarea>
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
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">{{ __('Registered plugins') }} ({{ $plugins->count() }} / {{ $totalRegistered }})</h2>
            <p class="muted" style="margin:4px 0 0;">{{ __('Inspect portals, compatibility, dependencies, health state, version history and retained migrations before activating, upgrading or uninstalling.') }}</p>
        </div>
        <form method="GET" action="{{ route('admin.plugins.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <select name="status" aria-label="{{ __('Filter by status') }}" style="width:auto;padding:8px 10px;">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['discovered', 'installed', 'active', 'inactive', 'update_staged', 'failed', 'uninstalled'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($statusFilter === $statusOption)>{{ ucfirst(str_replace('_', ' ', $statusOption)) }}</option>
                @endforeach
            </select>
            <select name="portal" aria-label="{{ __('Filter by portal') }}" style="width:auto;padding:8px 10px;">
                <option value="">{{ __('All portals') }}</option>
                @foreach(['admin', 'branch', 'teacher', 'student', 'guardian', 'support_staff', 'public'] as $portalOption)
                    <option value="{{ $portalOption }}" @selected($portalFilter === $portalOption)>{{ ucfirst(str_replace('_', ' ', $portalOption)) }}</option>
                @endforeach
            </select>
            <select name="type" aria-label="{{ __('Filter by type') }}" style="width:auto;padding:8px 10px;">
                <option value="">{{ __('All types') }}</option>
                @foreach(['module', 'integration', 'ai', 'theme'] as $typeOption)
                    <option value="{{ $typeOption }}" @selected($typeFilter === $typeOption)>{{ ucfirst($typeOption) }}</option>
                @endforeach
            </select>
            <button class="btn secondary" type="submit">{{ __('Filter') }}</button>
            @if($statusFilter !== '' || $portalFilter !== '' || $typeFilter !== '')
                <a class="btn secondary" href="{{ route('admin.plugins.index') }}">{{ __('Reset') }}</a>
            @endif
        </form>
    </div>

    @if($plugins->isEmpty())
        <p class="muted">{{ __('No plugins match the current filter, or none have been registered yet.') }}</p>
    @else
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Plugin') }}</th>
                        <th>{{ __('Version & History') }}</th>
                        <th>{{ __('Portals & Scope') }}</th>
                        <th>{{ __('Status & Compatibility') }}</th>
                        <th>{{ __('Health') }}</th>
                        <th>{{ __('Retention & Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($plugins as $plugin)
                    @php
                        $manifest = is_array($plugin->manifest) ? $plugin->manifest : [];
                        $compat = $compatibility[$plugin->plugin_key] ?? ['compatible' => true, 'errors' => []];
                        $preflight = $preflights[$plugin->plugin_key] ?? ['can_uninstall' => false, 'retention_summary' => '', 'completed_migrations' => 0];
                        $history = is_array($plugin->metadata['version_history'] ?? null) ? $plugin->metadata['version_history'] : [];
                        $healthBadge = match($plugin->health_status) {
                            'healthy' => 'success',
                            'degraded' => 'warning',
                            'failed' => 'warning',
                            default => 'info',
                        };
                        $statusBadge = match($plugin->status) {
                            'active', 'installed' => 'success',
                            'failed' => 'warning',
                            default => 'info',
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $plugin->name }}</strong>
                            <div class="muted"><code>{{ $plugin->plugin_key }}</code> · {{ ucfirst($plugin->type) }}</div>
                            @if(!empty($manifest['dependencies']))
                                <div class="muted"><small><strong>{{ __('Depends on:') }}</strong> {{ implode(', ', $manifest['dependencies']) }}</small></div>
                            @endif
                            @if(!empty($manifest['conflicts']))
                                <div class="muted"><small><strong>{{ __('Conflicts:') }}</strong> {{ implode(', ', $manifest['conflicts']) }}</small></div>
                            @endif
                        </td>
                        <td>
                            <code>{{ $plugin->version }}</code>
                            <div class="muted"><small>{{ $plugin->signature_verified_at ? __('Signed').' ('.$plugin->signature_key_id.')' : __('Not verified') }}</small></div>
                            @if($history !== [])
                                <div class="muted"><small>{{ count($history) }} {{ __('prior version(s)') }}</small></div>
                            @endif
                        </td>
                        <td>
                            <div><small>{{ !empty($manifest['portals']) ? implode(', ', $manifest['portals']) : '—' }}</small></div>
                            @if(!empty($manifest['permissions']))
                                <div class="muted"><small><strong>{{ __('Permissions:') }}</strong> {{ implode(', ', $manifest['permissions']) }}</small></div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', $plugin->status)) }}</span>
                            <div style="margin-top:4px;">
                                <span class="badge {{ $compat['compatible'] ? 'success' : 'warning' }}">
                                    {{ $compat['compatible'] ? __('Compatible') : __('Incompatible') }}
                                </span>
                            </div>
                            @if(!$compat['compatible'])
                                <div class="muted"><small>{{ implode(' ', $compat['errors']) }}</small></div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $healthBadge }}">{{ ucfirst($plugin->health_status ?? 'unknown') }}</span>
                            @if($plugin->health_message)
                                <div class="muted" style="max-width:240px;"><small>{{ $plugin->health_message }}</small></div>
                            @endif
                            <div class="muted"><small>{{ $plugin->last_health_check_at?->format('Y-m-d H:i') ?? __('Never checked') }}</small></div>
                        </td>
                        <td>
                            <div class="muted" style="margin-bottom:8px;"><small>{{ $preflight['retention_summary'] }}</small></div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                @if(in_array($plugin->status, ['installed', 'inactive'], true))
                                    <form method="POST" action="{{ route('admin.plugins.activate', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Activate this signed plugin? Its code will execute as trusted application code.') }}')">
                                        @csrf
                                        <button class="btn primary" type="submit">{{ __('Activate') }}</button>
                                    </form>
                                @elseif($plugin->status === 'active')
                                    <form method="POST" action="{{ route('admin.plugins.deactivate', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Deactivate this plugin? Its data and installed files will be retained.') }}')">
                                        @csrf
                                        <button class="btn secondary" type="submit">{{ __('Deactivate') }}</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.plugins.health', $plugin->plugin_key) }}" style="display:inline;">
                                    @csrf
                                    <button class="btn secondary" type="submit">{{ __('Health check') }}</button>
                                </form>

                                <a class="btn secondary" href="{{ route('admin.plugins.export', $plugin->plugin_key) }}">{{ __('Export JSON') }}</a>

                                @if(in_array($plugin->status, ['installed', 'inactive', 'failed'], true))
                                    <form method="POST" action="{{ route('admin.plugins.uninstall', $plugin->plugin_key) }}" style="display:inline;" onsubmit="return confirm('{{ __('Uninstall this plugin package? Conversation history, LMS data and migration records will be retained.') }}')">
                                        @csrf
                                        <button class="btn danger" type="submit" @disabled(!$preflight['can_uninstall'])>{{ __('Uninstall') }}</button>
                                    </form>
                                @endif
                            </div>

                            @if(in_array($plugin->status, ['installed', 'active', 'inactive'], true))
                                <details style="margin-top:10px;">
                                    <summary style="cursor:pointer;font-weight:600;">{{ __('Upgrade signed version') }}</summary>
                                    <form method="POST" action="{{ route('admin.plugins.update', $plugin->plugin_key) }}" enctype="multipart/form-data" style="margin-top:8px;padding:10px;border:1px solid var(--border);border-radius:10px;">
                                        @csrf
                                        <label>{{ __('New ZIP package') }}
                                            <input type="file" name="package" accept=".zip,application/zip" required>
                                        </label>
                                        <label>{{ __('Trusted release key ID') }}
                                            <input type="text" name="key_id" value="{{ $plugin->signature_key_id }}" maxlength="120" required>
                                        </label>
                                        <label>{{ __('Signed manifest JSON') }}
                                            <textarea name="manifest" rows="5" spellcheck="false" required></textarea>
                                        </label>
                                        <label>{{ __('Detached signature (Base64)') }}
                                            <textarea name="signature" rows="2" spellcheck="false" required></textarea>
                                        </label>
                                        <button class="btn primary" type="submit">{{ __('Verify and upgrade') }}</button>
                                    </form>
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

<section class="panel">
    <h2>{{ __('Recent plugin audit log') }}</h2>
    @if($auditLogs->isEmpty())
        <p class="muted">{{ __('No plugin lifecycle audit events recorded yet.') }}</p>
    @else
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Time') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($auditLogs as $log)
                    <tr>
                        <td style="white-space:nowrap;">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><code>{{ $log->event }}</code></td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
