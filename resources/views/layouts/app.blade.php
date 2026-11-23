<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('settings');
        $institutionName = $hasSettingsTable ? \App\Models\Setting::value('institution_name', config('app.name')) : config('app.name');
        $institutionShortName = $hasSettingsTable ? \App\Models\Setting::value('institution_short_name', '') : '';
        $institutionLogoUrl = $hasSettingsTable ? \App\Models\Setting::value('institution_logo_url') : null;
        $institutionFaviconUrl = $hasSettingsTable ? \App\Models\Setting::value('institution_favicon_url') : null;
        $institutionLogoMediaId = $hasSettingsTable ? (int) \App\Models\Setting::value('site_logo_media_id', 0) : 0;
        $institutionFaviconMediaId = $hasSettingsTable ? (int) \App\Models\Setting::value('site_favicon_media_id', 0) : 0;
        $loadingEnabled = $hasSettingsTable && \App\Models\Setting::value('loading_screen_enabled', '1') === '1';
        $loadingLogoMediaId = $hasSettingsTable ? (int) \App\Models\Setting::value('loading_screen_logo_media_id', 0) : 0;
        $loadingLogoMedia = $loadingLogoMediaId ? \App\Models\MediaAsset::query()->whereKey($loadingLogoMediaId)->where('mime_type', 'like', 'image/%')->first() : null;
        $loadingLogoUrl = $loadingLogoMedia?->url ?: $institutionLogoUrl;
        $loadingText = $hasSettingsTable ? \App\Models\Setting::value('loading_screen_text', 'Loading...') : 'Loading...';
        $loadingBackground = $hasSettingsTable ? \App\Models\Setting::value('loading_screen_background', '#ffffff') : '#ffffff';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $loadingBackground)) $loadingBackground = '#ffffff';
        $loadingStyle = $hasSettingsTable ? \App\Models\Setting::value('loading_screen_style', 'orbit') : 'orbit';
        if (!in_array($loadingStyle, ['orbit', 'pulse', 'ring'], true)) $loadingStyle = 'orbit';
        $loadingSpeed = $hasSettingsTable ? (int) \App\Models\Setting::value('loading_screen_speed', 12) : 12;
        $loadingSpeed = max(6, min(24, $loadingSpeed));
        $institutionLogoMedia = $institutionLogoMediaId ? \App\Models\MediaAsset::query()->whereKey($institutionLogoMediaId)->where('mime_type', 'like', 'image/%')->first() : null;
        $institutionFaviconMedia = $institutionFaviconMediaId ? \App\Models\MediaAsset::query()->whereKey($institutionFaviconMediaId)->where('mime_type', 'like', 'image/%')->first() : null;
        $institutionLogoUrl = $institutionLogoMedia?->url ?: $institutionLogoUrl;
        $institutionFaviconUrl = $institutionFaviconMedia?->url ?: $institutionFaviconUrl;
        $seoTitle = $hasSettingsTable ? \App\Models\Setting::value('seo_title', config('app.name')) : config('app.name');
        $seoDescription = $hasSettingsTable ? \App\Models\Setting::value('seo_description', 'Learn with guided courses, track your progress, and grow your skills.') : 'Learn with guided courses, track your progress, and grow your skills.';
        $seoKeywords = $hasSettingsTable ? \App\Models\Setting::value('seo_keywords', 'online learning, courses, education') : 'online learning, courses, education';
        $googleAnalyticsId = $hasSettingsTable ? \App\Models\Setting::value('google_analytics_id') : null;
        $googleTagManagerId = $hasSettingsTable ? \App\Models\Setting::value('google_tag_manager_id') : null;
        $facebookPixelId = $hasSettingsTable ? \App\Models\Setting::value('facebook_pixel_id') : null;
        $googleSearchConsoleVerification = $hasSettingsTable ? \App\Models\Setting::value('google_search_console_verification') : null;
        $marketingConsent = request()->cookie('marketing_consent') === 'accepted';
        $isBranchLayout = auth()->check() && auth()->user()->isBranchManager();
    @endphp
    @php
        $isAdminLayout = auth()->check() && in_array(auth()->user()->role, ['admin', 'branch_manager', 'accountant', 'teacher', 'student', 'guardian'], true);
        $studentTrackingPlugin = auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('plugin_registry')
            ? \App\Models\PluginInstallation::query()->where('plugin_key', 'aec.student-tracking')->where('status', 'active')->first()
            : null;
        $studentTrackingAvailable = $studentTrackingPlugin
            && in_array('student-tracking.view', $studentTrackingPlugin->metadata['granted_permissions'] ?? [], true);
        $isPublicLayout = ! $isAdminLayout;
        $websiteHeaderLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('header_links', '[]'), true) : [];
        $websitePageLinks = $isPublicLayout && \Illuminate\Support\Facades\Schema::hasTable('website_pages')
            ? \App\Models\WebsitePage::query()->where('status', 'published')->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get(['title', 'slug', 'menu_label'])
            : collect();
        $websiteFooterTitle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_title', config('app.name')) : config('app.name');
        $websiteFooterText = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_text', '') : '';
        $websiteFooterLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('footer_links', '[]'), true) : [];
        $themePrimary = $hasSettingsTable ? \App\Models\Setting::value('site_primary_color', '#1546a8') : '#1546a8';
        $themeBackground = $hasSettingsTable ? \App\Models\Setting::value('site_background_color', '#f3f6fb') : '#f3f6fb';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themePrimary)) $themePrimary = '#1546a8';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themeBackground)) $themeBackground = '#f3f6fb';
        $themeBackgroundStyle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_background_style', 'soft-gradient') : 'soft-gradient';
        $themeBackgrounds = ['solid' => $themeBackground, 'soft-gradient' => 'radial-gradient(circle at top left, rgba(21,70,168,0.10), transparent 30%), linear-gradient(180deg, ' . $themeBackground . ' 0%, #ffffff 100%)', 'warm-gradient' => 'radial-gradient(circle at top left, rgba(180,130,70,0.10), transparent 30%), linear-gradient(180deg, ' . $themeBackground . ' 0%, #fffdf9 100%)'];
        $themePageBackground = $themeBackgrounds[$themeBackgroundStyle] ?? $themeBackgrounds['soft-gradient'];
        $themeFont = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_font_family', 'system') : 'system';
        $themeFonts = ['system' => 'Arial, Helvetica, sans-serif', 'inter' => 'Inter, Arial, sans-serif', 'nunito' => 'Nunito, Arial, sans-serif', 'poppins' => 'Poppins, Arial, sans-serif', 'georgia' => 'Georgia, serif'];
        $themeFontFamily = $themeFonts[$themeFont] ?? $themeFonts['system'];
        // Guard the shared layout during first-install and rolling migrations.
        $hasThemesTable = \Illuminate\Support\Facades\Schema::hasTable('themes');
        $activeThemeSlug = $isPublicLayout && $hasSettingsTable && $hasThemesTable ? \App\Models\Setting::value('active_theme_slug') : null;
        $activeThemeVersion = $isPublicLayout && $hasSettingsTable && $hasThemesTable ? \App\Models\Setting::value('active_theme_version') : null;
        $themeHeadingScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_heading_scale', '1') : '1';
        $themeBodyScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_body_scale', '1') : '1';
        if (!in_array($themeHeadingScale, ['0.9', '1', '1.1', '1.2'], true)) $themeHeadingScale = '1';
        if (!in_array($themeBodyScale, ['0.9', '1', '1.05', '1.1'], true)) $themeBodyScale = '1';
        $pageSeoTitle = trim($__env->yieldContent('seo_title')) ?: (trim($__env->yieldContent('title')) ?: $seoTitle);
        $pageSeoDescription = trim($__env->yieldContent('seo_description')) ?: (trim($__env->yieldContent('meta_description')) ?: $seoDescription);
    @endphp
    @if($institutionFaviconUrl)<link rel="icon" href="{{ $institutionFaviconUrl }}">@endif
    @if($activeThemeSlug && $activeThemeVersion)
        @php $activeThemeRecord = \Illuminate\Support\Facades\DB::table('themes')->where('slug', $activeThemeSlug)->where('version', $activeThemeVersion)->first(); $activeThemeAssets = $activeThemeRecord ? json_decode($activeThemeRecord->assets, true) : []; @endphp
        @foreach(is_array($activeThemeAssets) ? $activeThemeAssets : [] as $activeThemeAsset)
            @if(is_string($activeThemeAsset) && preg_match('/^assets\\/[a-z0-9][a-z0-9._-]*\\.css$/i', $activeThemeAsset))
                <link rel="stylesheet" href="{{ route('themes.asset', ['slug' => $activeThemeSlug, 'version' => $activeThemeVersion, 'file' => basename($activeThemeAsset)]) }}">
            @endif
        @endforeach
    @endif
    <title>@yield('title', $pageSeoTitle)</title>
    <meta name="description" content="{{ $pageSeoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <link rel="canonical" href="@yield('canonical_url', request()->url())">
    <meta property="og:title" content="{{ $pageSeoTitle }}">
    <meta property="og:description" content="{{ $pageSeoDescription }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('canonical_url', request()->url())">
    <meta property="og:site_name" content="{{ $institutionName }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $pageSeoTitle }}">
    <meta name="twitter:description" content="{{ $pageSeoDescription }}">
    @if($googleSearchConsoleVerification)
        <meta name="google-site-verification" content="{{ $googleSearchConsoleVerification }}">
    @endif
    @if(auth()->check())
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if($marketingConsent && $googleAnalyticsId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($googleAnalyticsId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $googleAnalyticsId }}');
        </script>
    @endif
    @if($marketingConsent && $googleTagManagerId)
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $googleTagManagerId }}');
        </script>
    @endif
    @if($marketingConsent && $facebookPixelId)
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init','{{ $facebookPixelId }}');
            fbq('track','PageView');
            @if(session('meta_pixel_event') === 'CompleteRegistration')
                fbq('track','CompleteRegistration');
            @elseif(session('meta_pixel_event') === 'Lead')
                fbq('track','Lead');
            @endif
        </script>
        @stack('meta_pixel_events')
    @endif
    <style>
        :root {
            --bg: #f3f6fb;
            --panel: #ffffff;
            --panel-alt: #eef4ff;
            --primary: {{ $themePrimary }};
            --site-page-background: {!! $themePageBackground !!};
            --site-font-family: {{ $themeFontFamily }};
            --site-heading-scale: {{ $themeHeadingScale }};
            --site-body-scale: {{ $themeBodyScale }};
            --primary-dark: #0f2d6b;
            --green: #1f9d61;
            --text: #18212f;
            --muted: #5b6576;
            --border: #dfe7f6;
            --danger: #b42318;
            --shadow: 0 14px 34px rgba(21, 70, 168, 0.12);
        }

        * { box-sizing: border-box; }
        /* Keep off-canvas fixed navigation drawers from expanding the document's scrollable width. */
        html { overflow-x: clip; }
        body.admin-shell { background:#f5f7fb; }
        .admin-shell .topbar { margin-left:252px; }
        .admin-shell .nav { max-width:none; padding:14px 26px; }
        .admin-sidebar { position:fixed; inset:0 auto 0 0; z-index:30; width:252px; padding:24px 15px; overflow-y:auto; color:#dbe7ff; background:linear-gradient(180deg,#102b63,#0b1d42); }
        .admin-sidebar-brand { display:flex; align-items:center; gap:11px; padding:4px 10px 24px; color:#fff; font-weight:800; font-size:1rem; line-height:1.35; }
        .admin-sidebar-brand span:first-child { display:grid; place-items:center; width:39px; height:39px; border-radius:12px; background:#ffffff1c; font-size:1.2rem; }
        .admin-sidebar-label { margin:15px 10px 8px; color:#91a9d0; font-size:.68rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
        .admin-sidebar a { display:flex; align-items:center; gap:11px; padding:10px 12px; margin:3px 0; border-radius:10px; color:#dbe7ff; font-size:.88rem; font-weight:600; text-decoration:none; }
        .admin-sidebar a:hover,.admin-sidebar a.active { color:#fff; background:#ffffff1a; }
        .admin-sidebar a span:first-child { width:22px; text-align:center; font-size:1.05rem; }
        .admin-sidebar-footer { margin:22px 8px 0; padding:14px 10px; border-top:1px solid #ffffff25; color:#9fb3d4; font-size:.78rem; line-height:1.6; }
        .admin-shell .nav-links > a { display:none; }
        .admin-shell .nav-links > a[href*="dashboard"], .admin-shell .nav-links > a[href*="profile"] { display:inline-flex; }
        .admin-shell .nav-links > form { display:inline-flex; }
        .admin-shell .container { max-width:none; margin-left:252px; padding:28px 30px 55px; }
        @media(max-width:900px) { .admin-sidebar { width:76px; padding:20px 9px; } .admin-sidebar-brand { justify-content:center; padding:4px 0 22px; } .admin-sidebar-brand strong,.admin-sidebar-label,.admin-sidebar a .nav-text,.admin-sidebar-footer { display:none; } .admin-sidebar a { justify-content:center; padding:12px 5px; } .admin-sidebar a span:first-child { width:auto; } .admin-shell .topbar,.admin-shell .container { margin-left:76px; } .admin-shell .nav { padding:12px 16px; } }
        @media(max-width:600px) { .admin-sidebar { width:60px; } .admin-shell .topbar,.admin-shell .container { margin-left:60px; } .admin-shell .container { padding:18px 12px 40px; } .admin-shell .nav { padding:10px; } .admin-shell .brand { font-size:1rem; } }

        body {
            margin: 0;
            font-family: var(--site-font-family);
            font-size: calc(16px * var(--site-body-scale));
            background: var(--site-page-background);
            color: var(--text);
        }
        h1 { font-size: calc(2em * var(--site-heading-scale)); }
        h2 { font-size: calc(1.5em * var(--site-heading-scale)); }
        h3 { font-size: calc(1.17em * var(--site-heading-scale)); }
        h4 { font-size: calc(1em * var(--site-heading-scale)); }
        h5 { font-size: calc(.83em * var(--site-heading-scale)); }
        h6 { font-size: calc(.67em * var(--site-heading-scale)); }
        a { color: var(--primary); text-decoration: none; }
        .btn, .nav-links a, .nav-links button, .course-item, .panel, .card, .summary-box, .auth-box {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .course-item:hover, .summary-box:hover, .card:hover, .panel:hover, .auth-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(16, 36, 78, 0.08);
        }
        .topbar {
            background: rgba(255,255,255,0.95);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
            backdrop-filter: blur(8px);
        }
        .nav {
            max-width: 1200px; margin: 0 auto; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;
        }
        .brand {
            font-weight: 700; font-size: 1.4rem; color: var(--primary-dark);
        }
        .nav-links { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .nav-links a, .nav-links button {
            border: 1px solid var(--border); background: #fff; border-radius: 10px; padding: 9px 14px; color: var(--text); font-weight: 600; cursor: pointer;
        }
        .nav-links a:hover, .nav-links button:hover { background: var(--panel-alt); border-color: var(--primary); }
        .nav-links button { background: var(--primary); color: white; border-color: var(--primary); }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 24px 60px; }
        .hero {
            display: grid; grid-template-columns: 1.3fr 0.9fr; gap: 30px; background: linear-gradient(135deg, rgba(255,255,255,0.98), rgba(238,244,255,0.95)); border: 1px solid var(--border); border-radius: 24px; padding: 36px; box-shadow: var(--shadow);
        }
        .hero h1 { font-size: clamp(2rem, 2.8vw, 3.2rem); margin: 0 0 14px; color: var(--primary-dark); }
        .hero p { color: var(--muted); line-height: 1.7; margin: 0 0 24px; }
        .cta-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; padding: 12px 18px; font-weight: 700; border: 1px solid transparent; cursor: pointer; }
        .btn:hover, .nav-links a:hover, .nav-links button:hover { transform: translateY(-1px); }
        .btn.primary { background: var(--primary); color: white; box-shadow: 0 10px 18px rgba(21,70,168,0.18); }
        .btn.secondary { background: #fff; border-color: var(--border); color: var(--text); }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; margin-top: 22px; }
        .stat { background: var(--panel-alt); border: 1px solid var(--border); border-radius: 18px; padding: 18px; }
        .stat strong { display: block; font-size: 1.6rem; }
        .panel {
            background: var(--panel); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow); padding: 22px;
        }
        .grid { display: grid; gap: 20px; }
        .grid-2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0,1fr)); }
        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 16px; padding: 18px; }
        .card h3 { margin-top: 0; }
        .course-list { display: grid; gap: 16px; }
        .course-item { border: 1px solid var(--border); border-radius: 16px; padding: 18px; background: #fff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .badge.success { background: rgba(31,157,97,0.12); color: var(--green); }
        .badge.warning { background: rgba(255,167,38,0.12); color: #a25a00; }
        .badge.info { background: rgba(21,70,168,0.12); color: var(--primary); }
        .muted { color: var(--muted); }
        .dash-summary { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 16px; margin-bottom: 24px; }
        .summary-box { background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 20px; }
        .summary-box strong { display: block; font-size: 1.7rem; }
        form { display: block; }
        label { display: block; margin-bottom: 10px; font-weight: 700; }
        input, select, textarea {
            width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border); font-size: 1rem; background: #fff; font-family: inherit;
        }
        textarea { resize: vertical; min-height: 140px; }
        .auth-box {
            max-width: 480px; margin: 40px auto; background: #fff; border:1px solid var(--border); border-radius:20px; padding: 30px; box-shadow: var(--shadow);
        }
        .error { color: var(--danger); font-weight: 700; margin-top: 12px; }
        .flash { margin-bottom: 20px; padding: 12px 14px; border-radius: 10px; }
        .flash.success { background: rgba(31,157,97,0.12); color: #0f6b42; }
        .flash.info { background: rgba(21,70,168,0.12); color: var(--primary-dark); }
        .flash.error { background: rgba(180,35,24,0.12); color: var(--danger); }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
        .inline-form { display: inline; }
        .meta-row { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:18px; }
        @media (max-width: 820px) {
            .hero, .grid-2, .grid-3, .dash-summary { grid-template-columns: 1fr; }
            .nav { flex-direction: column; align-items: flex-start; }
            .nav-links { width: 100%; }
            .nav-links a, .nav-links button { flex: 1 1 calc(50% - 10px); text-align: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }

        .public-nav-trigger { display:inline-flex; align-items:center; gap:11px; min-height:46px; padding:10px 16px; border:0; border-radius:14px; color:#fff; background:linear-gradient(135deg,#1747a5,#0c2b69); box-shadow:0 10px 24px rgba(13,45,105,.2); font:700 .94rem Arial,sans-serif; cursor:pointer; }
        .public-nav-trigger:hover { transform:translateY(-1px); box-shadow:0 14px 28px rgba(13,45,105,.26); }
        .public-nav-trigger:focus-visible,.public-nav-close:focus-visible,.public-drawer a:focus-visible,.public-drawer button:focus-visible { outline:3px solid #f7b84b; outline-offset:3px; }
        .public-nav-trigger-icon { display:grid; gap:4px; width:19px; }
        .public-nav-trigger-icon i { display:block; height:2px; width:100%; border-radius:3px; background:currentColor; transition:transform .25s ease,opacity .2s ease; }
        .public-nav-backdrop { position:fixed; inset:0; z-index:80; background:rgba(6,18,43,.55); opacity:0; visibility:hidden; transition:opacity .28s ease,visibility .28s ease; backdrop-filter:blur(3px); }
        .public-nav-backdrop.is-open { opacity:1; visibility:visible; }
        .public-drawer { position:fixed; inset:0 0 0 auto; z-index:81; display:flex; flex-direction:column; width:min(430px,92vw); height:100dvh; padding:24px; overflow-y:auto; color:#eaf1ff; background:linear-gradient(160deg,#102e70 0%,#0a1d46 58%,#081632 100%); box-shadow:-24px 0 70px rgba(4,16,42,.28); transform:translateX(105%); visibility:hidden; transition:transform .34s cubic-bezier(.2,.8,.2,1),visibility .34s; overscroll-behavior:contain; }
        .public-drawer.is-open { transform:translateX(0); visibility:visible; }
        .public-drawer-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding-bottom:22px; border-bottom:1px solid rgba(255,255,255,.16); }
        .public-drawer-brand { display:flex; align-items:center; gap:12px; color:#fff; font-weight:800; font-size:1rem; line-height:1.25; }
        .public-drawer-brand-mark { display:grid; place-items:center; width:44px; height:44px; border-radius:14px; color:#12316e; background:#fff; font-size:1.35rem; }
        .public-drawer-brand small { display:block; margin-top:4px; color:#b9c9e8; font-size:.72rem; font-weight:500; }
        .public-nav-close { display:grid; place-items:center; width:42px; height:42px; border:1px solid rgba(255,255,255,.2); border-radius:13px; color:#fff; background:rgba(255,255,255,.08); font-size:1.5rem; cursor:pointer; }
        .public-drawer-intro { margin:22px 0 12px; color:#b9c9e8; font-size:.82rem; line-height:1.6; }
        .public-drawer-links { display:grid; gap:7px; }
        .public-drawer-links a { display:flex; align-items:center; gap:13px; min-height:48px; padding:11px 13px; border:1px solid transparent; border-radius:13px; color:#eaf1ff; font-weight:650; text-decoration:none; transition:background .2s ease,border-color .2s ease,transform .2s ease; }
        .public-drawer-links a:hover,.public-drawer-links a:focus-visible { transform:translateX(-3px); border-color:rgba(255,255,255,.14); color:#fff; background:rgba(255,255,255,.11); }
        .public-drawer-links .menu-icon { display:grid; place-items:center; width:30px; height:30px; flex:0 0 30px; border-radius:10px; color:#fff; background:rgba(255,255,255,.12); font-size:.95rem; }
        .public-drawer-section { margin:20px 0 9px; color:#8fa9d7; font-size:.68rem; font-weight:800; letter-spacing:.15em; text-transform:uppercase; }
        .public-drawer-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:22px; padding-top:18px; border-top:1px solid rgba(255,255,255,.16); }
        .public-drawer-actions a,.public-drawer-actions button { display:flex; align-items:center; justify-content:center; min-height:46px; padding:10px 14px; border:1px solid rgba(255,255,255,.24); border-radius:13px; color:#fff; background:rgba(255,255,255,.08); font:700 .9rem Arial,sans-serif; text-align:center; cursor:pointer; }
        .public-drawer-actions a.primary { border-color:#fff; color:#12316e; background:#fff; }
        .public-drawer-locale { margin-top:18px; padding-top:16px; border-top:1px solid rgba(255,255,255,.16); }
        .public-drawer-locale label { margin-bottom:8px; color:#b9c9e8; font-size:.78rem; }
        .public-drawer-locale select { color:#14284d; background:#fff; }
        body.public-menu-open { overflow:hidden; }
        @media(max-width:820px) { .nav { flex-direction:row; align-items:center; } .nav .brand { max-width:65%; font-size:1.1rem; } .public-nav-trigger { padding:10px 13px; } .container { padding:22px 16px 48px; } }
        @media(prefers-reduced-motion:reduce) { .public-nav-backdrop,.public-drawer,.public-nav-trigger-icon i,.public-drawer-links a { transition:none!important; } }

        /* Alom's unified LMS admin workspace */
        .admin-shell { --admin-sidebar-width:270px; --admin-toolbar-height:66px; }
        .admin-shell .admin-sidebar { width:var(--admin-sidebar-width); padding:18px 13px 22px; transition:width .22s ease,transform .22s ease; }
        .admin-shell .admin-sidebar-brand { min-height:54px; padding-bottom:18px; }
        .admin-menu-group { margin:7px 0 13px; }
        .admin-menu-group > summary { list-style:none; cursor:pointer; margin:0 8px 6px; padding:9px 8px; color:#8fa9d7; font-size:.68rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; border-radius:8px; }
        .admin-menu-group > summary::-webkit-details-marker { display:none; }
        .admin-menu-group > summary:after { content:'⌄'; float:right; font-size:.9rem; transition:transform .18s ease; }
        .admin-menu-group:not([open]) > summary:after { transform:rotate(-90deg); }
        .admin-menu-group > summary:hover { color:#fff; background:#ffffff10; }
        .admin-menu-group > a { margin:2px 0; }
        .admin-shell .topbar { margin-left:var(--admin-sidebar-width); min-height:var(--admin-toolbar-height); }
        .admin-shell .nav { min-height:var(--admin-toolbar-height); padding:10px 22px; gap:16px; }
        .admin-shell .nav > .brand { font-size:1.05rem; white-space:nowrap; }
        .admin-shell .nav-links { gap:9px; }
        .admin-shell .nav-links > a.admin-view-site { display:inline-flex; }
        .admin-shell .nav-links > a[href*="dashboard"], .admin-shell .nav-links > a[href*="profile"] { display:none; }
        .admin-toolbar-toggle { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border:1px solid var(--border); border-radius:10px; color:#23334d; background:#fff; font-size:1.15rem; cursor:pointer; }
        .admin-toolbar-toggle:hover { background:#eef4ff; border-color:#9db5e4; }
        .admin-shell .container { margin-left:var(--admin-sidebar-width); padding:28px clamp(18px,2.5vw,38px) 60px; min-height:calc(100vh - var(--admin-toolbar-height)); }
        .admin-sidebar-backdrop { display:none; }
        .admin-shell.admin-sidebar-collapsed { --admin-sidebar-width:78px; }
        .admin-sidebar-collapsed .admin-sidebar { padding-inline:9px; }
        .admin-sidebar-collapsed .admin-sidebar-brand { justify-content:center; padding-inline:0; }
        .admin-sidebar-collapsed .admin-sidebar-brand strong,
        .admin-sidebar-collapsed .admin-sidebar-label,
        .admin-sidebar-collapsed .admin-menu-group > summary,
        .admin-sidebar-collapsed .admin-sidebar a .nav-text,
        .admin-sidebar-collapsed .admin-sidebar-footer { display:none; }
        .admin-sidebar-collapsed .admin-menu-group { margin:6px 0; }
        .admin-sidebar-collapsed .admin-menu-group:not([open]) { display:none; }
        .admin-sidebar-collapsed .admin-sidebar a { justify-content:center; padding:11px 4px; }
        .admin-sidebar-collapsed .admin-sidebar a span:first-child { width:auto; }
        .admin-shell .admin-sidebar a:focus-visible,
        .admin-menu-group > summary:focus-visible,
        .admin-toolbar-toggle:focus-visible { outline:3px solid #f7c65c; outline-offset:2px; }
        @media(max-width:900px) {
            .admin-shell { --admin-sidebar-width:270px; }
            .admin-shell .admin-sidebar { width:270px; transform:translateX(-105%); box-shadow:16px 0 40px rgba(5,18,44,.2); }
            .admin-shell.admin-mobile-menu-open .admin-sidebar { transform:translateX(0); }
            .admin-shell .admin-sidebar-brand { justify-content:flex-start; }
            .admin-shell .admin-sidebar-brand strong,
            .admin-shell .admin-sidebar-label,
            .admin-shell .admin-menu-group > summary,
            .admin-shell .admin-sidebar a .nav-text,
            .admin-shell .admin-sidebar-footer { display:initial; }
            .admin-shell .admin-sidebar a { justify-content:flex-start; padding:10px 12px; }
            .admin-shell .admin-sidebar a span:first-child { width:22px; }
            .admin-shell .topbar,.admin-shell .container { margin-left:0; }
            .admin-shell .nav { flex-direction:row; align-items:center; }
            .admin-shell .nav-links { width:auto; margin-left:auto; flex-wrap:nowrap; }
            .admin-shell .nav-links form label { display:none; }
            .admin-shell .container { padding:22px 16px 48px; }
            .admin-sidebar-backdrop { display:block; position:fixed; inset:0; z-index:29; background:rgba(5,18,44,.48); opacity:0; visibility:hidden; transition:opacity .2s ease,visibility .2s ease; }
            .admin-mobile-menu-open .admin-sidebar-backdrop { opacity:1; visibility:visible; }
            .admin-shell.admin-sidebar-collapsed { --admin-sidebar-width:270px; }
        }
        @media(max-width:560px) {
            .admin-shell .nav { padding:9px 12px; gap:8px; }
            .admin-shell .nav > .brand { max-width:44vw; overflow:hidden; text-overflow:ellipsis; font-size:.92rem; }
            .admin-shell .nav-links { gap:6px; }
            .admin-shell .nav-links > a.admin-view-site { display:none; }
            .admin-shell .nav-links select { max-width:82px; padding:7px 5px!important; }
            .admin-shell .nav-links form.inline-form:last-child button { padding:8px 9px; font-size:.78rem; }
            .admin-toolbar-toggle { width:36px; height:36px; flex:0 0 36px; }
        }
        @media(prefers-reduced-motion:reduce) { .admin-shell .admin-sidebar,.admin-sidebar-backdrop,.admin-menu-group > summary:after { transition:none!important; } }

        /* Shared visual language for core LMS screens and plugin-owned screens */
        .admin-shell .page-header { padding:2px 0 18px; margin-bottom:22px; border-bottom:1px solid #e3e9f2; }
        .admin-shell .page-header h1,.admin-shell main h1 { color:#172b4d; letter-spacing:-.025em; }
        .admin-shell .panel,.admin-shell .card,.admin-shell .summary-box { border:1px solid #e2e8f1; border-radius:13px; box-shadow:0 3px 14px rgba(18,39,76,.045); }
        .admin-shell .panel:hover,.admin-shell .card:hover,.admin-shell .summary-box:hover { transform:none; box-shadow:0 7px 22px rgba(18,39,76,.075); }
        .admin-shell table { width:100%; border-collapse:separate; border-spacing:0; background:#fff; border:1px solid #e2e8f1; border-radius:12px; overflow:hidden; }
        .admin-shell thead th { padding:13px 15px; color:#52627a; background:#f5f7fb; border-bottom:1px solid #e2e8f1; font-size:.78rem; font-weight:800; letter-spacing:.035em; text-align:left; }
        .admin-shell tbody td { padding:13px 15px; border-bottom:1px solid #edf0f5; color:#27364d; vertical-align:middle; }
        .admin-shell tbody tr:last-child td { border-bottom:0; }
        .admin-shell tbody tr:hover td { background:#fafcff; }
        .admin-shell input:not([type=checkbox]):not([type=radio]),.admin-shell select,.admin-shell textarea { border-color:#d7dfeb; border-radius:9px; box-shadow:none; }
        .admin-shell input:focus,.admin-shell select:focus,.admin-shell textarea:focus { outline:0; border-color:#527bc4; box-shadow:0 0 0 3px rgba(50,103,190,.12); }
        .admin-shell .btn { border-radius:9px; font-weight:700; }
        .admin-shell .btn.primary { background:#17479d; }
        .admin-shell .btn.secondary { border-color:#d7dfeb; color:#263a5a; background:#fff; }
        .admin-shell .flash { border:1px solid transparent; border-radius:10px; }
        .admin-shell .dash-summary { gap:14px; }
        .admin-shell .summary-box strong { color:#173b7a; }
        @media(max-width:760px) {
            .admin-shell table { display:block; max-width:100%; overflow-x:auto; white-space:nowrap; }
            .admin-shell .page-header { gap:10px; }
            .admin-shell .panel { padding:16px; }
        }
        .aec-brand-loader { position:fixed; inset:0; z-index:99999; display:grid; place-items:center; background:var(--aec-loader-bg,#fff); opacity:1; visibility:visible; transition:opacity .22s ease,visibility .22s ease; }
        .aec-brand-loader[hidden] { display:none; }
        .aec-brand-loader.is-leaving { opacity:0; visibility:hidden; pointer-events:none; }
        .aec-brand-loader-inner { display:flex; flex-direction:column; align-items:center; gap:18px; padding:24px; text-align:center; }
        .aec-brand-loader-mark { position:relative; display:grid; place-items:center; width:104px; height:104px; }
        .aec-brand-loader-mark img { position:relative; z-index:2; width:68px; height:68px; object-fit:contain; border-radius:14px; }
        .aec-brand-loader-mark .aec-loader-orbit,.aec-brand-loader-mark .aec-loader-ring { position:absolute; inset:3px; border:3px solid transparent; border-top-color:var(--primary); border-right-color:var(--primary); border-radius:50%; animation:aec-loader-spin var(--aec-loader-speed,1s) linear infinite; }
        .aec-brand-loader-mark .aec-loader-orbit:after { content:''; position:absolute; width:9px; height:9px; border-radius:50%; background:var(--primary); top:8px; left:8px; }
        .aec-brand-loader-mark .aec-loader-ring { inset:10px; border-width:3px; border-right-color:transparent; border-bottom-color:var(--primary); }
        .aec-brand-loader[data-style="pulse"] .aec-brand-loader-mark img { animation:aec-loader-pulse var(--aec-loader-speed,1s) ease-in-out infinite; }
        .aec-brand-loader[data-style="pulse"] .aec-loader-orbit,.aec-brand-loader[data-style="pulse"] .aec-loader-ring { display:none; }
        .aec-brand-loader[data-style="ring"] .aec-loader-orbit { display:none; }
        .aec-brand-loader[data-style="ring"] .aec-loader-ring { display:block; }
        .aec-brand-loader-text { color:#334155; font-size:.94rem; font-weight:700; letter-spacing:.02em; }
        @keyframes aec-loader-spin { to { transform:rotate(360deg); } }
        @keyframes aec-loader-pulse { 0%,100% { transform:scale(.9); opacity:.72; } 50% { transform:scale(1.06); opacity:1; } }
        @media(prefers-reduced-motion:reduce) { .aec-brand-loader,.aec-brand-loader * { animation:none!important; transition:none!important; } }
    </style>
</head>
<body class="{{ $isAdminLayout ? 'admin-shell' : '' }}">
    @if($loadingEnabled)
        <div id="aec-brand-loader" class="aec-brand-loader" data-style="{{ $loadingStyle }}" style="--aec-loader-bg:{{ $loadingBackground }};--aec-loader-speed:{{ number_format(12 / $loadingSpeed, 2) }}s" role="status" aria-live="polite" aria-label="{{ $loadingText ?: __('Loading') }}">
            <div class="aec-brand-loader-inner">
                <div class="aec-brand-loader-mark">
                    @if($loadingLogoUrl)<img src="{{ $loadingLogoUrl }}" alt="">@else<span style="font-size:2.8rem;font-weight:800;color:var(--primary);">{{ mb_substr($institutionName, 0, 1) }}</span>@endif
                    <span class="aec-loader-orbit" aria-hidden="true"></span><span class="aec-loader-ring" aria-hidden="true"></span>
                </div>
                @if($loadingText)<span class="aec-brand-loader-text">{{ $loadingText }}</span>@endif
            </div>
        </div>
    @endif
    @if($marketingConsent && $googleTagManagerId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($googleTagManagerId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @if($isAdminLayout)
        <aside class="admin-sidebar" aria-label="Admin navigation">
            <a class="admin-sidebar-brand" href="{{ $isBranchLayout ? route('branch.dashboard') : route('dashboard') }}">@if($institutionLogoUrl)<img src="{{ $institutionLogoUrl }}" alt="{{ $institutionName }}" style="width:39px;height:39px;object-fit:contain;border-radius:10px;background:#fff;padding:3px;">@else<span>📘</span>@endif<strong>{{ $institutionName }}<br><small style="font-weight:500;color:#a9bee0;">{{ auth()->user()->isAdmin() ? ($institutionShortName ?: __('Admin Panel')) : (auth()->user()->isBranchManager() ? __('Branch Panel') : (auth()->user()->isTeacher() ? __('Teacher Panel') : (auth()->user()->isStudent() ? __('Student Panel') : (auth()->user()->isGuardian() ? __('Guardian Panel') : __('Finance workspace'))))) }}</small></strong></a>
            <a class="{{ request()->routeIs('notices.*') ? 'active' : '' }}" href="{{ route('notices.index') }}"><span>🔔</span><span class="nav-text">Notice Board</span></a>
            @if(auth()->user()->isAdmin())
            <div class="admin-sidebar-label">Overview</div>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><span class="nav-text">Dashboard</span></a>
            <div class="admin-sidebar-label">Academy</div>
            <a href="{{ route('admin.academic-levels.index') }}"><span>🎓</span><span class="nav-text">Academic levels</span></a>
            <a href="{{ route('admin.courses.index') }}"><span>📚</span><span class="nav-text">Courses & lessons</span></a>
            <a class="{{ request()->routeIs('admin.ebooks.*') ? 'active' : '' }}" href="{{ route('admin.ebooks.index') }}"><span>📕</span><span class="nav-text">eBook management</span></a>
            <a class="{{ request()->routeIs('admin.ebooks.orders.*') ? 'active' : '' }}" href="{{ route('admin.ebooks.orders.index') }}"><span>🧾</span><span class="nav-text">eBook orders</span></a>
            <a class="{{ request()->routeIs('admin.course-combos.*') ? 'active' : '' }}" href="{{ route('admin.course-combos.index') }}"><span>🎁</span><span class="nav-text">Course combos</span></a>
            <a class="{{ request()->routeIs('admin.course-categories.*') ? 'active' : '' }}" href="{{ route('admin.course-categories.index') }}"><span>🗂️</span><span class="nav-text">Course categories</span></a>
            <a class="{{ request()->routeIs('admin.course-content-posts.*') ? 'active' : '' }}" href="{{ route('admin.course-content-posts.index') }}"><span>📝</span><span class="nav-text">Course content studio</span></a>
            <a class="{{ request()->routeIs('admin.question-sets.*') ? 'active' : '' }}" href="{{ route('admin.question-sets.index') }}"><span>🧩</span><span class="nav-text">Question sets</span></a>
            <a class="{{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><span>🏷️</span><span class="nav-text">Coupons</span></a>
            @if(\Illuminate\Support\Facades\Route::has('admin.flash-sales.index') && \App\Models\FlashSale::isPluginActive())
                <a href="{{ route('admin.flash-sales.index') }}"><span>🔥</span><span class="nav-text">Flash Sale</span></a>
            @endif
            <a href="{{ route('admin.users.index') }}"><span>👥</span><span class="nav-text">Students & teachers</span></a>
            @if($studentTrackingAvailable && \Illuminate\Support\Facades\Route::has('admin.student-tracking.index'))
                <a class="{{ request()->routeIs('admin.student-tracking.*') ? 'active' : '' }}" href="{{ route('admin.student-tracking.index') }}"><span>📊</span><span class="nav-text">Student Tracking</span></a>
            @endif
            <a class="{{ request()->routeIs('admin.teacher-applications.*') ? 'active' : '' }}" href="{{ route('admin.teacher-applications.index') }}"><span>🪪</span><span class="nav-text">Teacher KYC review</span></a>
            <a class="{{ request()->routeIs('admin.student-devices.*') ? 'active' : '' }}" href="{{ route('admin.student-devices.index') }}"><span>📱</span><span class="nav-text">Device approvals</span></a>
            <a class="{{ request()->routeIs('admin.verification-center.*') ? 'active' : '' }}" href="{{ route('admin.verification-center.index') }}"><span>✅</span><span class="nav-text">Verification center</span></a>
            <a class="{{ request()->routeIs('admin.security.sessions.*') ? 'active' : '' }}" href="{{ route('admin.security.sessions.index') }}"><span>🔐</span><span class="nav-text">Device sessions</span></a>
            <a href="{{ route('admin.batches.index') }}"><span>🗓️</span><span class="nav-text">Batches & branches</span></a>
            <a href="{{ route('admin.contests.index') }}"><span>🎁</span><span class="nav-text">Quiz & lottery</span></a>
            @if(auth()->user()->isAdmin())
            <a class="{{ request()->routeIs('admin.ai-developer.*') ? 'active' : '' }}" href="{{ route('admin.ai-developer.index') }}"><span>🤖</span><span class="nav-text">AI Developer</span></a>
            @endif
            <a href="{{ route('live-classes.index') }}"><span>🎥</span><span class="nav-text">Live classes</span></a>
            <div class="admin-sidebar-label">Finance</div>
            <a href="{{ route('admin.payments.index') }}"><span>💳</span><span class="nav-text">Payments</span></a>
            <a href="{{ route('admin.invoices.index') }}"><span>🧾</span><span class="nav-text">Invoices</span></a>
            <a href="{{ route('admin.refunds.index') }}"><span>↩️</span><span class="nav-text">Refunds</span></a>
            <a href="{{ route('admin.expenses.index') }}"><span>💸</span><span class="nav-text">Expenses & reports</span></a>
            <div class="admin-sidebar-label">Website & tools</div>
            <a class="{{ request()->routeIs('admin.cms.*') ? 'active' : '' }}" href="{{ route('admin.cms.index') }}"><span>🧭</span><span class="nav-text">CMS Control Center</span></a>
            <a class="{{ request()->routeIs('admin.modules.*') ? 'active' : '' }}" href="{{ route('admin.modules.index') }}"><span>🧩</span><span class="nav-text">Module Manager</span></a>
            <a class="{{ request()->routeIs('admin.plugins.*') ? 'active' : '' }}" href="{{ route('admin.plugins.index') }}"><span>🧰</span><span class="nav-text">Plugin Manager</span></a>
            <a class="{{ request()->routeIs('admin.themes.*') ? 'active' : '' }}" href="{{ route('admin.themes.index') }}"><span>🎨</span><span class="nav-text">Theme Manager</span></a>
            <a href="{{ route('admin.website-builder.index') }}"><span>🖥️</span><span class="nav-text">Website builder</span></a>
            <a href="{{ route('admin.website-pages.index') }}"><span>📄</span><span class="nav-text">Custom pages</span></a>
            <a href="{{ route('admin.media.index') }}"><span>🖼️</span><span class="nav-text">Media library</span></a>
            <a href="{{ route('admin.storage.index') }}"><span>🗄️</span><span class="nav-text">Storage manager</span></a>
            <a href="{{ route('admin.news.index') }}"><span>📰</span><span class="nav-text">Blog & news</span></a>
            <a class="{{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.index') }}"><span>📈</span><span class="nav-text">Website analytics</span></a>
            <a class="{{ request()->routeIs('admin.marketing.*') ? 'active' : '' }}" href="{{ route('admin.marketing.index') }}"><span>📣</span><span class="nav-text">Marketing & Boost</span></a>
            <a class="{{ request()->routeIs('admin.marketing.meta-ads.*') ? 'active' : '' }}" href="{{ route('admin.marketing.meta-ads.index') }}"><span>🔗</span><span class="nav-text">Meta Ads integration</span></a>
            <a class="{{ request()->routeIs('admin.comments.*') ? 'active' : '' }}" href="{{ route('admin.comments.index') }}"><span>💬</span><span class="nav-text">Comment moderation</span></a>
            <a class="{{ request()->routeIs('admin.support-tickets.*') ? 'active' : '' }}" href="{{ route('admin.support-tickets.index') }}"><span>🎧</span><span class="nav-text">Support tickets</span></a>
            @if(\Illuminate\Support\Facades\Route::has('admin.visitor-popups.index'))
                <a class="{{ request()->routeIs('admin.visitor-popups.*') ? 'active' : '' }}" href="{{ route('admin.visitor-popups.index') }}"><span>📣</span><span class="nav-text">Popup Ads</span></a>
            @endif
            <a class="{{ request()->routeIs('admin.dashboard-announcements.*') ? 'active' : '' }}" href="{{ route('admin.dashboard-announcements.index') }}"><span>🔔</span><span class="nav-text">Dashboard announcements</span></a>
            <a href="{{ route('admin.backups.index') }}"><span>💾</span><span class="nav-text">Backup & restore</span></a>
            <a href="{{ route('admin.activity-logs.index') }}"><span>📋</span><span class="nav-text">Activity logs</span></a>
            <a href="{{ route('admin.settings.index') }}"><span>⚙️</span><span class="nav-text">Settings</span></a>
            <a href="{{ route('admin.sms-campaigns.index') }}"><span>✉️</span><span class="nav-text">SMS campaigns</span></a>
            @elseif(auth()->user()->isBranchManager())
                <div class="admin-sidebar-label">Branch Panel</div>
                <a class="{{ request()->routeIs('branch.dashboard') ? 'active' : '' }}" href="{{ route('branch.dashboard') }}"><span>▦</span><span class="nav-text">Branch dashboard</span></a>
                @if(auth()->user()->hasAdminPermission('branch.batches') || auth()->user()->hasAdminPermission('branch.students'))
                    <div class="admin-sidebar-label">Branch operations</div>
                    @if(auth()->user()->hasAdminPermission('branch.batches'))
                        <a class="{{ request()->routeIs('branch.batches.*') ? 'active' : '' }}" href="{{ route('branch.batches.index') }}"><span>🗓️</span><span class="nav-text">My branch batches</span></a>
                    @endif
                    @if(auth()->user()->hasAdminPermission('branch.students'))
                        <a class="{{ request()->routeIs('branch.students.*') ? 'active' : '' }}" href="{{ route('branch.students.index') }}"><span>👥</span><span class="nav-text">Branch students</span></a>
                    @endif
                    @if($studentTrackingAvailable && \Illuminate\Support\Facades\Route::has('branch.student-tracking.index'))
                        <a class="{{ request()->routeIs('branch.student-tracking.*') ? 'active' : '' }}" href="{{ route('branch.student-tracking.index') }}"><span>📊</span><span class="nav-text">Student Tracking</span></a>
                    @endif
                @endif
            @elseif(auth()->user()->isTeacher())
                <div class="admin-sidebar-label">Teacher Panel</div>
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><span class="nav-text">Teacher dashboard</span></a>
                <a class="{{ request()->routeIs('teacher.batches.*') ? 'active' : '' }}" href="{{ route('teacher.batches.index') }}"><span>🗓️</span><span class="nav-text">{{ __('My batches & classes') }}</span></a>
                <a class="{{ request()->routeIs('live-classes.*') ? 'active' : '' }}" href="{{ route('live-classes.index') }}"><span>🎥</span><span class="nav-text">Live classes</span></a>
            @elseif(auth()->user()->isStudent())
                <div class="admin-sidebar-label">Student Panel</div>
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><span class="nav-text">Student dashboard</span></a>
                <a class="{{ request()->routeIs('student.courses.*') ? 'active' : '' }}" href="{{ route('student.courses.index') }}"><span>📚</span><span class="nav-text">My courses</span></a>
                <a class="{{ request()->routeIs('student.exams.*') ? 'active' : '' }}" href="{{ route('student.exams.index') }}"><span>📝</span><span class="nav-text">My exams</span></a>
                <a class="{{ request()->routeIs('student.question-set-attempts.*') ? 'active' : '' }}" href="{{ route('student.question-set-attempts.index') }}"><span>🧩</span><span class="nav-text">Quiz history</span></a>
                <a class="{{ request()->routeIs('student.invoices.*') ? 'active' : '' }}" href="{{ route('student.invoices.index') }}"><span>🧾</span><span class="nav-text">My invoices</span></a>
                @if(\Illuminate\Support\Facades\Route::has('student.attendance.index'))
                    <a class="{{ request()->routeIs('student.attendance.*') ? 'active' : '' }}" href="{{ route('student.attendance.index') }}"><span>📅</span><span class="nav-text">My attendance</span></a>
                @endif
                <a class="{{ request()->routeIs('student.guardian-requests.*') ? 'active' : '' }}" href="{{ route('student.guardian-requests.page') }}"><span>👨‍👩‍👧</span><span class="nav-text">Guardian requests</span></a>
                <a href="{{ route('live-classes.index') }}"><span>🎥</span><span class="nav-text">Live classes</span></a>
            @elseif(auth()->user()->isGuardian())
                <div class="admin-sidebar-label">Guardian Panel</div>
                <a class="{{ request()->routeIs('guardian.dashboard') ? 'active' : '' }}" href="{{ route('guardian.dashboard') }}"><span>▦</span><span class="nav-text">Guardian dashboard</span></a>
                <a class="{{ request()->routeIs('guardian.links.*') ? 'active' : '' }}" href="{{ route('guardian.dashboard') }}"><span>👨‍👩‍👧</span><span class="nav-text">My children</span></a>
            @elseif(auth()->user()->isAccountant())
                <div class="admin-sidebar-label">Overview</div>
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><span class="nav-text">Dashboard</span></a>
                @if(auth()->user()->hasAdminPermission('finance.invoices') || auth()->user()->hasAdminPermission('finance.refunds') || auth()->user()->hasAdminPermission('finance.expenses') || auth()->user()->hasAdminPermission('finance.reports'))
                    <div class="admin-sidebar-label">Finance workspace</div>
                    @if(auth()->user()->hasAdminPermission('finance.invoices'))
                        <a class="{{ request()->routeIs('admin.finance.invoices.*') ? 'active' : '' }}" href="{{ route('admin.finance.invoices.index') }}"><span>🧾</span><span class="nav-text">Branch invoices</span></a>
                    @endif
                    @if(auth()->user()->hasAdminPermission('finance.refunds'))
                        <a class="{{ request()->routeIs('admin.finance.refunds.*') ? 'active' : '' }}" href="{{ route('admin.finance.refunds.index') }}"><span>↩️</span><span class="nav-text">Refund requests</span></a>
                    @endif
                    @if(auth()->user()->hasAdminPermission('finance.expenses'))
                        <a class="{{ request()->routeIs('admin.finance.expenses.*') ? 'active' : '' }}" href="{{ route('admin.finance.expenses.index') }}"><span>💸</span><span class="nav-text">Branch expenses</span></a>
                    @endif
                    @if(auth()->user()->hasAdminPermission('finance.reports'))
                        <a class="{{ request()->routeIs('admin.finance.reports.*') ? 'active' : '' }}" href="{{ route('admin.finance.reports.index') }}"><span>📊</span><span class="nav-text">Branch finance reports</span></a>
                    @endif
                @endif
            @endif
            <div class="admin-sidebar-footer">{{ $institutionName }}<br>{{ $hasSettingsTable ? \App\Models\Setting::value('institution_tagline', __('Learning Management System')) : __('Learning Management System') }}</div>
        </aside>
    @endif
    <header class="topbar">
        <nav class="nav">
            <a href="{{ auth()->check() ? (auth()->user()->isBranchManager() ? route('branch.dashboard') : route('dashboard')) : '/' }}" class="brand">@if($institutionLogoUrl)<img src="{{ $institutionLogoUrl }}" alt="" style="height:32px;max-width:140px;object-fit:contain;vertical-align:middle;margin-right:8px;">@endif{{ $institutionName }}</a>
            @if($isAdminLayout)
            <div class="nav-links">
                <button type="button" class="admin-toolbar-toggle" id="admin-sidebar-toggle" aria-label="Toggle admin navigation" aria-expanded="true">☰</button>
                <a class="admin-view-site" href="/" target="_blank" rel="noopener">{{ __("View site") }} ↗</a>
                <form method="POST" action="{{ route('locale.update') }}" class="inline-form" style="display:inline-flex;gap:6px;align-items:center;">
                    @csrf
                    <label for="locale-switch" class="muted" style="margin:0;">{{ __('Language') }}</label>
                    <select id="locale-switch" name="locale" onchange="this.form.submit()" style="width:auto;padding:8px 10px;">
                        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('English') }}</option>
                        <option value="bn" @selected(app()->getLocale() === 'bn')>{{ __('Bangla') }}</option>
                    </select>
                </form>
                <a href="/">{{ __('Home') }}</a>
                <a href="{{ route('courses.index') }}">{{ __('Courses') }}</a>
                <a href="{{ route('ebooks.index') }}">eBooks</a>
                @if(\Illuminate\Support\Facades\Route::has('flash-sales.public') && \App\Models\FlashSale::isPluginActive() && (bool) (int) \App\Models\Setting::value('flash_sale_page_enabled', '1') && \App\Models\FlashSale::activeCampaigns()->isNotEmpty())<a href="{{ route('landing') }}#flash-sale">🔥 {{ __('Flash Sale') }}</a>@endif
                <a href="{{ route('contests.index') }}">Quiz & prizes</a>
                <a href="{{ route('pages.about') }}">{{ __('About') }}</a>
                <a href="{{ route('pages.contact') }}">{{ __('Contact') }}</a>
                <a href="{{ route('pages.faq') }}">{{ __('FAQ') }}</a>
                <a href="{{ route('news.index') }}">{{ __('Blog & News') }}</a>
                
                    @foreach(is_array($websiteHeaderLinks) ? $websiteHeaderLinks : [] as $websiteLink)
                        @if(is_array($websiteLink) && !empty($websiteLink['label']) && !empty($websiteLink['url']))
                            <a href="{{ $websiteLink['url'] }}">{{ $websiteLink['label'] }}</a>
                        @endif
                    @endforeach
                @auth
                    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>                    @foreach($websitePageLinks as $websitePageLink)
                        <a href="{{ route('website-pages.show', $websitePageLink->slug) }}">{{ $websitePageLink->menu_label ?: $websitePageLink->title }}</a>
                    @endforeach

                    <a href="{{ route('notices.index') }}">{{ __('Notice Board') }}</a>
                    <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                    <a href="{{ route('live-classes.index') }}">{{ __('Live classes') }}</a>
                    @if(auth()->user()->isStudent())
                        <a href="{{ route('ebooks.orders') }}">My eBook library</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.batches.index') }}">Batches</a>
                        <a href="{{ route('admin.sms-campaigns.index') }}">SMS</a>
                        <a href="{{ route('admin.payments.index') }}">Payments</a>
                        <a href="{{ route('admin.invoices.index') }}">Invoices</a>
                        <a href="{{ route('admin.refunds.index') }}">Refunds</a>
                        <a href="{{ route('admin.expenses.index') }}">Expenses</a>
                        <a href="{{ route('admin.finance-reports.index') }}">Finance reports</a>
                        <a href="{{ route('admin.activity-logs.index') }}">Activity log</a>
                        <a href="{{ route('admin.settings.index') }}">Settings</a>
                        <a href="{{ route('admin.website-builder.index') }}">{{ __('Website Builder') }}</a>
                        <a href="{{ route('admin.backups.index') }}">{{ __('Backup & Restore') }}</a>
                        <a href="{{ route('admin.media.index') }}">{{ __('Media Library') }}</a>
                        <a href="{{ route('admin.storage.index') }}">{{ __('Storage Manager') }}</a>
                        <a href="{{ route('admin.news.index') }}">{{ __('Blog & News') }}</a>
                        <a href="{{ route('admin.users.index') }}">Users</a>
                        <a href="{{ route('admin.courses.index') }}">Courses</a>
                        @if(\Illuminate\Support\Facades\Route::has('admin.flash-sales.index') && \App\Models\FlashSale::isPluginActive())
                            <a href="{{ route('admin.flash-sales.index') }}">Flash Sale</a>
                        @endif
                    @endif
                    @if(auth()->user()->isTeacher())
                        <a href="{{ route('teacher.batches.index') }}">My batches</a>
                        @if($studentTrackingAvailable && \Illuminate\Support\Facades\Route::has('teacher.student-tracking.index'))
                            <a href="{{ route('teacher.student-tracking.index') }}">My Students</a>
                        @endif
                    @endif
                    @if(auth()->user()->isGuardian())
                        <a href="{{ route('guardian.dashboard') }}">{{ __('Guardian portal') }}</a>
                        @if($studentTrackingAvailable && \Illuminate\Support\Facades\Route::has('guardian.student-tracking.index'))
                            <a href="{{ route('guardian.student-tracking.index') }}">Children Tracking</a>
                        @endif
                    @endif
                    @if(auth()->user()->isStudent())
                        <a href="{{ route('student.guardian-requests.page') }}">{{ __('Guardian requests') }}</a>
                        <a href="{{ route('student.wishlist.index') }}">Wishlist</a>
                        <a href="{{ route('student.attendance.index') }}">Attendance</a>
                        @if($studentTrackingAvailable && \Illuminate\Support\Facades\Route::has('student.tracking.index'))
                            <a href="{{ route('student.tracking.index') }}">My Learning Tracking</a>
                        @endif
                    <a href="{{ route('student.exams.index') }}">Exams</a>
                        <a href="{{ route('student.invoices.index') }}">My fees</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="inline-form">
                        @csrf
                        <button type="submit">{{ __('Logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}">{{ __('Register') }}</a>
                    <a href="{{ route('teacher.apply') }}">Teacher apply</a>
                @endauth
            </div>
            @else
                @include('partials.public-navigation')
            @endif
        </nav>
    </header>

    <main class="container">
        @if(session('success'))
            <div class="flash success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="flash info">{{ session('info') }}</div>
        @endif
        @if(session()->has('admin_mfa_recovery_codes'))
            <section class="flash warning" role="alert" aria-live="assertive">
                <h2>{{ __('Save your administrator recovery codes') }}</h2>
                <p>{{ __('These codes are shown only once. Store them somewhere private and secure. Each code can be used once.') }}</p>
                <ul>
                    @foreach(session('admin_mfa_recovery_codes', []) as $recoveryCode)
                        <li><code>{{ $recoveryCode }}</code></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if(session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
    
        <footer class="container" style="display:{{ $isAdminLayout ? 'none' : 'block' }};padding-top:24px;padding-bottom:36px;border-top:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                <div style="max-width:620px;">
                    <strong>{{ $websiteFooterTitle }}</strong>
                    @if($websiteFooterText !== '')
                        <p class="muted" style="margin:8px 0 0;line-height:1.7;">{{ $websiteFooterText }}</p>
                    @endif
                </div>
                <nav aria-label="{{ __('Footer navigation') }}" style="display:flex;gap:12px;flex-wrap:wrap;">
                    @foreach(is_array($websiteFooterLinks) ? $websiteFooterLinks : [] as $websiteLink)
                        @if(is_array($websiteLink) && !empty($websiteLink['label']) && !empty($websiteLink['url']))
                            <a href="{{ $websiteLink['url'] }}">{{ $websiteLink['label'] }}</a>
                        @endif
                    @endforeach
                </nav>
            </div>
        </footer>
    @if(!request()->cookie('marketing_consent'))
        <aside id="cookie-preferences" class="panel" style="position:fixed; right:20px; bottom:20px; z-index:20; max-width:440px; box-shadow:var(--shadow);">
            <strong>Cookie preferences</strong>
            <p class="muted" style="margin:8px 0 14px;">Allow analytics and marketing cookies? Essential site features work either way.</p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn primary" onclick="saveMarketingConsent('accepted')">Accept analytics</button>
                <button type="button" class="btn secondary" onclick="saveMarketingConsent('declined')">Reject optional cookies</button>
            </div>
        </aside>
    @else
        <button type="button" class="btn secondary" style="position:fixed; right:16px; bottom:16px; z-index:20;" onclick="saveMarketingConsent('')">Cookie settings</button>
    @endif
    <script>
        function saveMarketingConsent(choice) {
            const secure = window.location.protocol === 'https:' ? '; Secure' : '';
            const value = choice ? `marketing_consent=${choice}; Max-Age=15552000` : 'marketing_consent=; Max-Age=0';
            document.cookie = `${value}; Path=/; SameSite=Lax${secure}`;
            window.location.reload();
        }
    </script>

    <script>
        (() => {
            const trigger = document.getElementById('public-nav-trigger');
            const drawer = document.getElementById('public-navigation-drawer');
            const backdrop = document.getElementById('public-nav-backdrop');
            if (!trigger || !drawer || !backdrop) return;
            const closeButton = drawer.querySelector('[data-menu-close]');
            let previousFocus = null;
            const openMenu = () => {
                previousFocus = document.activeElement;
                drawer.classList.add('is-open');
                backdrop.classList.add('is-open');
                document.body.classList.add('public-menu-open');
                trigger.setAttribute('aria-expanded', 'true');
                drawer.setAttribute('aria-hidden', 'false');
                backdrop.setAttribute('aria-hidden', 'false');
                window.setTimeout(() => { if (closeButton) closeButton.focus(); }, 30);
            };
            const closeMenu = () => {
                drawer.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                document.body.classList.remove('public-menu-open');
                trigger.setAttribute('aria-expanded', 'false');
                drawer.setAttribute('aria-hidden', 'true');
                backdrop.setAttribute('aria-hidden', 'true');
                if (previousFocus && previousFocus.focus) previousFocus.focus();
            };
            trigger.addEventListener('click', () => trigger.getAttribute('aria-expanded') === 'true' ? closeMenu() : openMenu());
            if (closeButton) closeButton.addEventListener('click', closeMenu);
            backdrop.addEventListener('click', closeMenu);
            drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeMenu();
                if (event.key === 'Tab' && drawer.classList.contains('is-open')) {
                    const focusable = Array.from(drawer.querySelectorAll('a[href],button:not([disabled]),select')).filter(el => el.offsetParent !== null);
                    if (!focusable.length) return;
                    const first = focusable[0], last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                }
            });
        })();
    </script>
    @if((! auth()->check() || ! in_array(auth()->user()->role, ['admin', 'branch_manager', 'accountant'], true)) && app()->bound('popup_ads.render'))
        {!! app('popup_ads.render')(request()) !!}
    @endif

    <script>
        (() => {
            const shell = document.body;
            const sidebar = document.querySelector('.admin-sidebar');
            const toggle = document.getElementById('admin-sidebar-toggle');
            if (!sidebar || !toggle) return;
            const headings = Array.from(sidebar.querySelectorAll(':scope > .admin-sidebar-label'));
            headings.forEach((heading, index) => {
                const group = document.createElement('details');
                group.className = 'admin-menu-group';
                const summary = document.createElement('summary');
                summary.textContent = heading.textContent.trim();
                summary.setAttribute('aria-label', summary.textContent + ' menu');
                heading.replaceWith(group);
                group.appendChild(summary);
                while (group.nextSibling && !(group.nextSibling.nodeType === 1 && group.nextSibling.matches('.admin-sidebar-label,.admin-sidebar-footer'))) {
                    group.appendChild(group.nextSibling);
                }
                group.open = Boolean(group.querySelector('a.active')) || index === 0;
            });
            const backdrop = document.createElement('div');
            backdrop.className = 'admin-sidebar-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            sidebar.before(backdrop);
            const mobile = () => window.matchMedia('(max-width: 900px)').matches;
            const setExpanded = expanded => {
                toggle.setAttribute('aria-expanded', String(expanded));
                toggle.setAttribute('aria-label', mobile() ? (expanded ? 'Close admin navigation' : 'Open admin navigation') : (expanded ? 'Collapse admin navigation' : 'Expand admin navigation'));
            };
            const closeMobile = () => { shell.classList.remove('admin-mobile-menu-open'); setExpanded(false); };
            toggle.addEventListener('click', () => {
                if (mobile()) {
                    const open = shell.classList.toggle('admin-mobile-menu-open');
                    setExpanded(open);
                    return;
                }
                const collapsed = shell.classList.toggle('admin-sidebar-collapsed');
                const groups = Array.from(sidebar.querySelectorAll('.admin-menu-group'));
                groups.forEach((group, index) => {
                    group.open = collapsed || Boolean(group.querySelector('a.active')) || index === 0;
                });
                try { localStorage.setItem('aec-admin-sidebar-collapsed', collapsed ? '1' : '0'); } catch (_) {}
                setExpanded(!collapsed);
            });
            backdrop.addEventListener('click', closeMobile);
            sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { if (mobile()) closeMobile(); }));
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && shell.classList.contains('admin-mobile-menu-open')) closeMobile(); });
            try {
                if (!mobile() && localStorage.getItem('aec-admin-sidebar-collapsed') === '1') {
                    shell.classList.add('admin-sidebar-collapsed');
                    sidebar.querySelectorAll('.admin-menu-group').forEach(group => { group.open = true; });
                    setExpanded(false);
                } else setExpanded(!mobile());
            } catch (_) { setExpanded(!mobile()); }
            window.addEventListener('resize', () => {
                if (!mobile()) closeMobile();
                setExpanded(mobile() ? shell.classList.contains('admin-mobile-menu-open') : !shell.classList.contains('admin-sidebar-collapsed'));
            });
        })();
    </script>
    @if($loadingEnabled)
        <script>
            (() => {
                const loader = document.getElementById('aec-brand-loader');
                if (!loader) return;
                let shownAt = Date.now();
                const hide = () => {
                    const wait = Math.max(0, 260 - (Date.now() - shownAt));
                    window.setTimeout(() => { loader.classList.add('is-leaving'); window.setTimeout(() => loader.hidden = true, 240); }, wait);
                };
                const show = () => { shownAt = Date.now(); loader.hidden = false; loader.classList.remove('is-leaving'); };
                if (document.readyState === 'complete') hide();
                else window.addEventListener('load', hide, { once:true });
                window.addEventListener('pageshow', hide);
                document.addEventListener('submit', event => {
                    const form = event.target;
                    if (form instanceof HTMLFormElement && form.method.toLowerCase() !== 'dialog' && !form.hasAttribute('data-no-page-loader')) show();
                }, true);
                document.addEventListener('click', event => {
                    const link = event.target.closest('a[href]');
                    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    if (link.target && link.target !== '_self' || link.hasAttribute('download')) return;
                    const url = new URL(link.href, window.location.href);
                    if (url.origin !== window.location.origin || url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
                    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
                    show();
                }, true);
                window.addEventListener('beforeunload', show);
            })();
        </script>
    @endif
    @stack('scripts')
</body>
</html>
