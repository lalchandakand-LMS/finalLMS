@extends('layouts.app')

@section('title', __('CMS Control Center') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Website administration') }}</span>
        <h1>{{ __('CMS Control Center') }}</h1>
        <p class="muted">{{ __('Manage your public website, content, appearance and extensions from one workspace.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('landing') }}" target="_blank" rel="noopener">{{ __('View public website') }}</a>
</div>

<div class="grid grid-4" style="margin-bottom:22px;">
    @foreach([
        ['label' => __('Pages'), 'value' => $counts['pages'], 'detail' => __('Published: :count', ['count' => $counts['published_pages']]), 'icon' => '📄'],
        ['label' => __('Blog posts'), 'value' => $counts['posts'], 'detail' => __('Published: :count', ['count' => $counts['published_posts']]), 'icon' => '📰'],
        ['label' => __('Media files'), 'value' => $counts['media'], 'detail' => __('Central media library'), 'icon' => '🖼️'],
        ['label' => __('Menus'), 'value' => $counts['menus'], 'detail' => __('Navigation items'), 'icon' => '🧭'],
        ['label' => __('Installed plugins'), 'value' => $counts['plugins'], 'detail' => __('Active: :count', ['count' => $counts['active_plugins']]), 'icon' => '🧩'],
        ['label' => __('Installed themes'), 'value' => $counts['themes'], 'detail' => __('Active: :count', ['count' => $counts['active_themes']]), 'icon' => '🎨'],
    ] as $stat)
        <article class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;"><span class="muted">{{ $stat['label'] }}</span><span aria-hidden="true">{{ $stat['icon'] }}</span></div>
            <strong style="display:block;font-size:1.8rem;margin-top:8px;">{{ number_format($stat['value']) }}</strong>
            <small class="muted">{{ $stat['detail'] }}</small>
        </article>
    @endforeach
</div>

<section class="panel" style="margin-bottom:22px;">
    <h2>{{ __('Website management') }}</h2>
    <p class="muted">{{ __('Create and publish content, manage navigation and control how the public website looks.') }}</p>
    <div class="grid grid-3">
        <a class="card" href="{{ route('admin.website-builder.index') }}"><strong>🖥️ {{ __('Website Builder') }}</strong><p class="muted">{{ __('Edit homepage sections, branding, layout and drafts.') }}</p></a>
        <a class="card" href="{{ route('admin.website-pages.index') }}"><strong>📄 {{ __('Pages') }}</strong><p class="muted">{{ __('Create, edit, preview, publish and restore page revisions.') }}</p></a>
        <a class="card" href="{{ route('admin.news.index') }}"><strong>📰 {{ __('Posts & News') }}</strong><p class="muted">{{ __('Manage articles, drafts, publishing and SEO content.') }}</p></a>
        <a class="card" href="{{ route('admin.website-menu.index') }}"><strong>🧭 {{ __('Navigation Menus') }}</strong><p class="muted">{{ __('Organize public navigation links and menu order.') }}</p></a>
        <a class="card" href="{{ route('admin.media.index') }}"><strong>🖼️ {{ __('Media Library') }}</strong><p class="muted">{{ __('Upload and manage reusable website assets.') }}</p></a>
        <a class="card" href="{{ route('admin.analytics.index') }}"><strong>📈 {{ __('Website Analytics') }}</strong><p class="muted">{{ __('Review website traffic and engagement reports.') }}</p></a>
    </div>
</section>

<section class="panel" style="margin-bottom:22px;">
    <h2>{{ __('Appearance & extensions') }}</h2>
    <p class="muted">{{ __('Control themes and installable extensions from the same administration workspace.') }}</p>
    <div class="grid grid-3">
        <a class="card" href="{{ route('admin.themes.index') }}"><strong>🎨 {{ __('Themes') }}</strong><p class="muted">{{ __('Install trusted themes, preview, activate or reset safely.') }}</p></a>
        <a class="card" href="{{ route('admin.plugins.index') }}"><strong>🧩 {{ __('Plugin Manager') }}</strong><p class="muted">{{ __('Install, configure, activate, update and manage plugin permissions.') }}</p></a>
        <a class="card" href="{{ route('admin.modules.index') }}"><strong>🧰 {{ __('Module Manager') }}</strong><p class="muted">{{ __('Review built-in LMS module availability.') }}</p></a>
        <a class="card" href="{{ route('admin.settings.index') }}"><strong>⚙️ {{ __('Website Settings') }}</strong><p class="muted">{{ __('Manage institution identity, localization, SEO and site-wide preferences.') }}</p></a>
        <a class="card" href="{{ route('admin.storage.index') }}"><strong>🗄️ {{ __('Storage Manager') }}</strong><p class="muted">{{ __('Review storage usage and uploaded files.') }}</p></a>
        <a class="card" href="{{ route('admin.backups.index') }}"><strong>💾 {{ __('Backup & Restore') }}</strong><p class="muted">{{ __('Create backups and review restore controls before major changes.') }}</p></a>
    </div>
</section>

<div class="grid grid-2">
    <section class="panel">
        <div class="page-header"><div><h2>{{ __('Recently updated pages') }}</h2><p class="muted">{{ __('Latest website page changes.') }}</p></div><a href="{{ route('admin.website-pages.index') }}">{{ __('View all') }}</a></div>
        @forelse($recentPages as $page)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                <div><strong>{{ $page->title }}</strong><div class="muted">/pages/{{ $page->slug }}</div></div>
                <span class="badge {{ $page->status === 'published' ? 'success' : 'secondary' }}">{{ __(ucfirst($page->status)) }}</span>
            </div>
        @empty
            <p class="muted">{{ __('No website pages have been created yet.') }}</p>
        @endforelse
    </section>
    <section class="panel">
        <div class="page-header"><div><h2>{{ __('Recently updated posts') }}</h2><p class="muted">{{ __('Latest article changes.') }}</p></div><a href="{{ route('admin.news.index') }}">{{ __('View all') }}</a></div>
        @forelse($recentPosts as $post)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                <div><strong>{{ $post->title }}</strong><div class="muted">/news/{{ $post->slug }}</div></div>
                <span class="badge {{ $post->status === 'published' ? 'success' : 'secondary' }}">{{ __(ucfirst($post->status)) }}</span>
            </div>
        @empty
            <p class="muted">{{ __('No blog posts have been created yet.') }}</p>
        @endforelse
    </section>
</div>
@endsection
