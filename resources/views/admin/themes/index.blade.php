@extends('layouts.app')
@section('title', __('Theme Manager'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Appearance') }}</span>
        <h1>{{ __('Theme Manager') }}</h1>
        <p class="muted">{{ __('Install trusted visual themes, preview them safely, activate or return to the built-in Alom LMS theme.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.website-builder.index') }}">{{ __('Website Builder') }}</a>
</div>

@if(!$signingConfigured)
    <div class="panel" style="border-color:#d69e2e;margin-bottom:20px;">
        <strong>{{ __('Signed theme installation is not configured yet') }}</strong>
        <p class="muted">{{ __('Set THEME_PUBLIC_KEY on the server to the institution public key. Until then, ZIP installation remains disabled. Never upload or store the private signing key on this server.') }}</p>
    </div>
@endif

<div class="panel" style="margin-bottom:22px;">
    <h2>{{ __('Install Theme ZIP') }}</h2>
    <p class="muted">{{ __('Only Alom LMS theme packages with a valid institutional signature are accepted. Themes may contain declarative CSS only; PHP, JavaScript, templates and executable files are rejected.') }}</p>
    <form method="POST" action="{{ route('admin.themes.install') }}" enctype="multipart/form-data">
        @csrf
        <label for="theme-package">{{ __('Theme package (.zip)') }}</label>
        <input id="theme-package" type="file" name="package" accept=".zip,application/zip" required @disabled(!$signingConfigured)>
        @error('package')<p class="error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit" style="margin-top:14px;" @disabled(!$signingConfigured)>{{ __('Verify and install') }}</button>
    </form>
</div>

<div class="page-header">
    <div><h2>{{ __('Installed Themes') }}</h2><p class="muted">{{ __('Preview does not change the live website. Activation keeps the previous theme in history.') }}</p></div>
    <form method="POST" action="{{ route('admin.themes.reset') }}" onsubmit="return confirm('{{ __('Reset the public website to the built-in Alom LMS theme? Your pages and content will remain unchanged.') }}')">
        @csrf
        <button class="btn secondary" type="submit">{{ __('Reset to default theme') }}</button>
    </form>
</div>

@if($themes->isEmpty())
    <div class="panel"><p class="muted">{{ __('No additional themes are installed. The built-in Alom LMS theme is active.') }}</p></div>
@else
    <div class="grid grid-3">
        @foreach($themes as $theme)
            <article class="card">
                @if($theme->preview_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($theme->preview_path) }}" alt="{{ $theme->name }} {{ __('theme preview') }}" style="width:100%;height:170px;object-fit:cover;border-radius:12px;">
                @else
                    <div style="height:170px;border-radius:12px;background:linear-gradient(135deg,#e8efff,#fff);display:grid;place-items:center;color:#1546a8;font-weight:800;">{{ $theme->name }}</div>
                @endif
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-top:12px;">
                    <h3 style="margin:0;">{{ $theme->name }}</h3>
                    @if((int)$theme->id === $activeThemeId)<span class="badge success">{{ __('Active') }}</span>@endif
                </div>
                <p class="muted">{{ __('Version') }} {{ $theme->version }} · {{ $theme->author ?: __('Alom LMS theme') }}</p>
                @if($theme->description)<p>{{ $theme->description }}</p>@endif
                <div class="cta-row" style="margin-top:14px;">
                    <a class="btn secondary" href="{{ route('admin.themes.preview', $theme->id) }}">{{ __('Preview') }}</a>
                    @if((int)$theme->id !== $activeThemeId)
                        <form method="POST" action="{{ route('admin.themes.activate', $theme->id) }}" onsubmit="return confirm('{{ __('Activate this theme on the public website?') }}')">
                            @csrf
                            <button class="btn primary" type="submit">{{ __('Activate') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.themes.destroy', $theme->id) }}" onsubmit="return confirm('{{ __('Permanently delete this inactive theme and its package files? Website content and activation history will be retained.') }}')">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit" style="color:#b42318;border-color:#b42318;">{{ __('Delete') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif

<div class="panel" style="margin-top:24px;">
    <h2>{{ __('Activation History') }}</h2>
    @if($history->isEmpty())
        <p class="muted">{{ __('No theme changes have been recorded yet.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table">
                <thead><tr>
                    <th>{{ __('Action') }}</th>
                    <th>{{ __('Theme') }}</th>
                    <th>{{ __('Previous theme') }}</th>
                    <th>{{ __('Changed by') }}</th>
                    <th>{{ __('Date') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($history as $entry)
                        @php($previous = json_decode((string) $entry->previous_state, true) ?: [])
                        <tr>
                            <td>{{ __(ucfirst($entry->action)) }}</td>
                            <td>{{ $entry->action === 'delete' ? ($previous['name'] ?? $previous['slug'] ?? __('Deleted theme')) : ($entry->theme_name ?: __('Built-in Alom LMS theme')) }}</td>
                            <td>{{ $entry->action === 'delete' ? '—' : (($previous['slug'] ?? __('Built-in Alom LMS theme')).' '.($previous['version'] ?? '')) }}</td>
                            <td>{{ $entry->actor_name ?: __('System') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted">{{ __('To roll back, preview and activate a previously installed theme, or reset to the built-in theme.') }}</p>
    @endif
</div>
@endsection
