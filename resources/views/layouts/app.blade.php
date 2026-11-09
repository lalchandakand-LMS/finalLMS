<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('settings');
        $seoTitle = $hasSettingsTable ? \App\Models\Setting::value('seo_title', config('app.name')) : config('app.name');
        $seoDescription = $hasSettingsTable ? \App\Models\Setting::value('seo_description', 'Learn with guided courses, track your progress, and grow your skills.') : 'Learn with guided courses, track your progress, and grow your skills.';
        $seoKeywords = $hasSettingsTable ? \App\Models\Setting::value('seo_keywords', 'online learning, courses, education') : 'online learning, courses, education';
        $googleAnalyticsId = $hasSettingsTable ? \App\Models\Setting::value('google_analytics_id') : null;
        $googleTagManagerId = $hasSettingsTable ? \App\Models\Setting::value('google_tag_manager_id') : null;
        $facebookPixelId = $hasSettingsTable ? \App\Models\Setting::value('facebook_pixel_id') : null;
        $marketingConsent = request()->cookie('marketing_consent') === 'accepted';
        $themePrimary = $hasSettingsTable ? \App\Models\Setting::value('theme_primary', '#C2410C') : '#C2410C';
        $themeSecondary = $hasSettingsTable ? \App\Models\Setting::value('theme_secondary', '#9A3412') : '#9A3412';
        $themeAccent = $hasSettingsTable ? \App\Models\Setting::value('theme_accent', '#FED7AA') : '#FED7AA';
        $themeSidebarStart = $hasSettingsTable ? \App\Models\Setting::value('theme_sidebar_start', '#7C2D12') : '#7C2D12';
        $themeSidebarEnd = $hasSettingsTable ? \App\Models\Setting::value('theme_sidebar_end', '#431407') : '#431407';
        $isPublicLayout = !auth()->check();
        $websiteSiteName = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_name', config('app.name')) : config('app.name');
        $websitePrimary = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_primary_color', $themePrimary) : $themePrimary;
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $websitePrimary)) $websitePrimary = $themePrimary;
        $websiteBackground = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_background_color', '#FFF7ED') : '#FFF7ED';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $websiteBackground)) $websiteBackground = '#FFF7ED';
        $websiteBackgroundStyle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_background_style', 'soft-gradient') : 'soft-gradient';
        $websiteBackgrounds = [
            'solid' => $websiteBackground,
            'soft-gradient' => 'radial-gradient(circle at top left, rgba(194,65,12,0.10), transparent 30%), linear-gradient(180deg, '.$websiteBackground.' 0%, #ffffff 100%)',
            'warm-gradient' => 'radial-gradient(circle at top left, rgba(180,100,40,0.12), transparent 30%), linear-gradient(180deg, '.$websiteBackground.' 0%, #fffdf9 100%)',
        ];
        $websitePageBackground = $websiteBackgrounds[$websiteBackgroundStyle] ?? $websiteBackgrounds['soft-gradient'];
        $websiteFontKey = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_font_family', 'system') : 'system';
        $websiteFonts = ['system' => 'Arial, Helvetica, sans-serif', 'inter' => 'Inter, Arial, sans-serif', 'nunito' => 'Nunito, Arial, sans-serif', 'poppins' => 'Poppins, Arial, sans-serif', 'georgia' => 'Georgia, serif'];
        $websiteFontFamily = $websiteFonts[$websiteFontKey] ?? $websiteFonts['system'];
        $websiteHeadingScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_heading_scale', '1') : '1';
        $websiteBodyScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_body_scale', '1') : '1';
        if (!in_array($websiteHeadingScale, ['0.9', '1', '1.1', '1.2'], true)) $websiteHeadingScale = '1';
        if (!in_array($websiteBodyScale, ['0.9', '1', '1.05', '1.1'], true)) $websiteBodyScale = '1';
        $websiteHeaderLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('header_links', '[]'), true) : [];
        $websiteHeaderLinks = is_array($websiteHeaderLinks) ? array_values(array_filter($websiteHeaderLinks, fn ($link) => is_array($link) && !empty($link['label']) && !empty($link['url']) && preg_match('~^(https?://|/(?!/)|#)~i', trim($link['url'])))) : [];
        $websiteFooterTitle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_title', $websiteSiteName) : $websiteSiteName;
        $websiteFooterText = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_text', '') : '';
        $websiteFooterLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('footer_links', '[]'), true) : [];
        $websiteFooterLinks = is_array($websiteFooterLinks) ? array_values(array_filter($websiteFooterLinks, fn ($link) => is_array($link) && !empty($link['label']) && !empty($link['url']) && preg_match('~^(https?://|/(?!/)|#)~i', trim($link['url'])))) : [];
        $websiteLogo = $isPublicLayout && $hasSettingsTable ? \App\Models\MediaAsset::query()->whereKey(\App\Models\Setting::value('site_logo_media_id'))->where('mime_type', 'like', 'image/%')->first() : null;
        $websiteFavicon = $isPublicLayout && $hasSettingsTable ? \App\Models\MediaAsset::query()->whereKey(\App\Models\Setting::value('site_favicon_media_id'))->where('mime_type', 'like', 'image/%')->first() : null;
    @endphp
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    @if($websiteFavicon)<link rel="icon" href="{{ $websiteFavicon->url }}">@endif
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
        </script>
    @endif
    @php($hasAppSidebar = auth()->check())
    <style>
        :root {
            --bg: #f3f6fb;
            --panel: #ffffff;
            --panel-alt: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $themeAccent) ? $themeAccent : '#FED7AA' }};
            --accent: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $themeAccent) ? $themeAccent : '#FED7AA' }};
            --sidebar-start: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $themeSidebarStart) ? $themeSidebarStart : '#7C2D12' }};
            --sidebar-end: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $themeSidebarEnd) ? $themeSidebarEnd : '#431407' }};
            --primary: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $isPublicLayout ? $websitePrimary : $themePrimary) ? ($isPublicLayout ? $websitePrimary : $themePrimary) : '#C2410C' }};
            --site-page-background: {!! $websitePageBackground !!};
            --site-font-family: {{ $websiteFontFamily }};
            --site-heading-scale: {{ $websiteHeadingScale }};
            --site-body-scale: {{ $websiteBodyScale }};
            --primary-dark: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $themeSecondary) ? $themeSecondary : '#9A3412' }};
            --green: #1f9d61;
            --text: #18212f;
            --muted: #5b6576;
            --border: #dfe7f6;
            --danger: #b42318;
            --shadow: 0 14px 34px rgba(21, 70, 168, 0.12);
        }

        * { box-sizing: border-box; }
        body:not(.app-shell) { background:var(--site-page-background); font-family:var(--site-font-family); font-size:calc(16px * var(--site-body-scale)); }
        body:not(.app-shell) h1,body:not(.app-shell) h2,body:not(.app-shell) h3 { font-size:calc(1em * var(--site-heading-scale)); }
        
        body.app-shell { background:#f4f7fc; }
        .app-sidebar { position:fixed; inset:0 auto 0 0; z-index:40; width:268px; padding:20px 14px; overflow-y:auto; color:#dce8ff; background:linear-gradient(180deg,var(--sidebar-start),var(--sidebar-end)); box-shadow:8px 0 30px rgba(9,25,58,.12); }
        .app-sidebar-brand { display:flex; align-items:center; gap:11px; padding:6px 10px 20px; color:#fff; font-weight:800; text-decoration:none; border-bottom:1px solid #ffffff20; }
        .brand-mark { display:grid; place-items:center; flex:0 0 40px; width:40px; height:40px; border-radius:12px; background:#ffffff1c; }
        .brand-copy small { color:#a9bee0; font-size:.73rem; font-weight:500; }
        .app-sidebar-section { margin:19px 10px 7px; color:#91a9d0; font-size:.66rem; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        .app-sidebar a.menu-link { display:flex; align-items:center; gap:12px; min-height:41px; padding:9px 11px; margin:3px 0; border-radius:10px; color:#dbe7ff; font-size:.86rem; font-weight:600; text-decoration:none; }
        .app-sidebar a.menu-link:hover,.app-sidebar a.menu-link.active { color:#fff; background:linear-gradient(90deg,var(--primary),#ffffff24); }
        .app-sidebar a.menu-link.active { box-shadow:inset 3px 0 #83b5ff; }
        .menu-icon { display:grid; place-items:center; flex:0 0 23px; width:23px; font-size:1.03rem; }
        .app-sidebar-footer { margin:22px 8px 0; padding:14px 10px 4px; border-top:1px solid #ffffff25; color:#9fb3d4; font-size:.75rem; line-height:1.6; }
        .app-shell .topbar { position:relative; top:auto; margin-left:268px; }
        .app-shell .nav { max-width:none; padding:13px 26px; gap:16px; }
        .app-shell .nav .brand { font-size:1.08rem; }
        .app-shell .nav-links { gap:8px; }
        .app-shell .nav-links > a { display:none; }
        .app-shell .nav-links > form { display:inline-flex; }
        .app-shell .container { max-width:none; margin-left:268px; padding:28px 30px 55px; }
        .sidebar-toggle { display:none; border:1px solid var(--border); background:#fff; color:var(--primary-dark); border-radius:10px; width:40px; height:40px; font-size:1.25rem; cursor:pointer; }
        .sidebar-overlay { display:none; }
        @media(max-width:900px) {
            .app-sidebar { width:76px; padding:18px 8px; }
            .app-sidebar-brand { justify-content:center; padding:5px 0 18px; }
            .brand-copy,.app-sidebar-section,.app-sidebar a.menu-link .menu-text,.app-sidebar-footer { display:none; }
            .app-sidebar a.menu-link { justify-content:center; padding:11px 4px; }
            .menu-icon { flex-basis:auto; }
            .app-shell .topbar,.app-shell .container { margin-left:76px; }
            .app-shell .nav { padding:11px 14px; }
            .app-shell .container { padding:22px 18px 45px; }
        }
        @media(max-width:600px) {
            .app-sidebar { width:270px; transform:translateX(-102%); transition:transform .24s ease; padding:18px 14px; }
            .app-sidebar.is-open { transform:translateX(0); }
            .app-sidebar.is-open .brand-copy,.app-sidebar.is-open .app-sidebar-section,.app-sidebar.is-open a.menu-link .menu-text,.app-sidebar.is-open .app-sidebar-footer { display:block; }
            .app-sidebar.is-open a.menu-link { justify-content:flex-start; padding:9px 11px; }
            .app-sidebar.is-open .menu-icon { flex-basis:23px; }
            .sidebar-overlay.is-visible { display:block; position:fixed; inset:0; z-index:35; background:rgba(8,20,42,.48); }
            .app-shell .topbar,.app-shell .container { margin-left:0; }
            .app-shell .nav { flex-direction:row; align-items:center; padding:10px 12px; }
            .app-shell .nav .brand { font-size:.92rem; }
            .app-shell .nav-links { width:auto; margin-left:auto; }
            .app-shell .nav-links label { display:none; }
            .app-shell .container { padding:18px 12px 40px; }
            .sidebar-toggle { display:inline-grid; place-items:center; flex:0 0 40px; }
        }
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
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(21,70,168,0.12), transparent 28%),
                linear-gradient(180deg, #eef5ff 0%, #f9fbff 100%);
            color: var(--text);
        }
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
    </style>
</head>
<body class="{{ $hasAppSidebar ? 'app-shell' : '' }}">
    @if($marketingConsent && $googleTagManagerId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($googleTagManagerId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif


    @auth
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleAppSidebar(false)"></div>
        <aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
            <a class="app-sidebar-brand" href="{{ route('dashboard') }}"><span class="brand-mark">📘</span><span class="brand-copy">Alom’s English Center<br><small>{{ auth()->user()->isAdmin() ? 'Admin workspace' : (auth()->user()->isTeacher() ? 'Teacher workspace' : (auth()->user()->isStudent() ? 'Student learning' : (auth()->user()->isBranchManager() ? 'Branch workspace' : 'Finance workspace'))) }}</small></span></a>
            <div class="app-sidebar-section">Workspace</div>
            <a class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="menu-icon">▦</span><span class="menu-text">Dashboard</span></a>
            @if(auth()->user()->isAdmin())
                <div class="app-sidebar-section">Academy management</div>
                <a class="menu-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}" href="{{ route('admin.courses.index') }}"><span class="menu-icon">📚</span><span class="menu-text">Courses & lessons</span></a>
                <a class="menu-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><span class="menu-icon">🛍️</span><span class="menu-text">Products & eBooks</span></a>
                <a class="menu-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><span class="menu-icon">📦</span><span class="menu-text">Commerce orders</span></a>
                <a class="menu-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><span class="menu-icon">🏷️</span><span class="menu-text">Coupons</span></a>
                <a class="menu-link {{ request()->routeIs('admin.flash-sales.*') ? 'active' : '' }}" href="{{ route('admin.flash-sales.index') }}"><span class="menu-icon">🔥</span><span class="menu-text">Flash sales</span></a>
                <a class="menu-link {{ request()->routeIs('admin.question-sets.*') ? 'active' : '' }}" href="{{ route('admin.question-sets.index') }}"><span class="menu-icon">📝</span><span class="menu-text">Question sets</span></a>
                <a class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="menu-icon">👥</span><span class="menu-text">Students & teachers</span></a>
                <a class="menu-link {{ request()->routeIs('admin.batches.*','admin.branches.*') ? 'active' : '' }}" href="{{ route('admin.batches.index') }}"><span class="menu-icon">🗓️</span><span class="menu-text">Batches & branches</span></a>
                <a class="menu-link {{ request()->routeIs('admin.contests.*') ? 'active' : '' }}" href="{{ route('admin.contests.index') }}"><span class="menu-icon">🎁</span><span class="menu-text">Quiz & contests</span></a>
                <a class="menu-link {{ request()->routeIs('live-classes.*') ? 'active' : '' }}" href="{{ route('live-classes.index') }}"><span class="menu-icon">🎥</span><span class="menu-text">Live classes</span></a>
                <div class="app-sidebar-section">Finance & payments</div>
                <a class="menu-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><span class="menu-icon">💳</span><span class="menu-text">Payments</span></a>
                <a class="menu-link {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}" href="{{ route('admin.invoices.index') }}"><span class="menu-icon">🧾</span><span class="menu-text">Invoices</span></a>
                <a class="menu-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}" href="{{ route('admin.refunds.index') }}"><span class="menu-icon">↩️</span><span class="menu-text">Refunds</span></a>
                <a class="menu-link {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" href="{{ route('admin.expenses.index') }}"><span class="menu-icon">💸</span><span class="menu-text">Expenses</span></a>
                <a class="menu-link {{ request()->routeIs('admin.finance-reports.*') ? 'active' : '' }}" href="{{ route('admin.finance-reports.index') }}"><span class="menu-icon">📊</span><span class="menu-text">Finance reports</span></a>
                <div class="app-sidebar-section">Website & system</div>
                <a class="menu-link {{ request()->routeIs('admin.website-builder.*') ? 'active' : '' }}" href="{{ route('admin.website-builder.index') }}"><span class="menu-icon">🖥️</span><span class="menu-text">Website builder</span></a>
                <a class="menu-link {{ request()->routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.media.index') }}"><span class="menu-icon">🖼️</span><span class="menu-text">Media library</span></a>
                <a class="menu-link {{ request()->routeIs('admin.news.*') ? 'active' : '' }}" href="{{ route('admin.news.index') }}"><span class="menu-icon">📰</span><span class="menu-text">Blog & news</span></a>
                <a class="menu-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" href="{{ route('admin.backups.index') }}"><span class="menu-icon">💾</span><span class="menu-text">Backup & restore</span></a>
                <a class="menu-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}"><span class="menu-icon">📋</span><span class="menu-text">Activity logs</span></a>
                <a class="menu-link {{ request()->routeIs('admin.sms-campaigns.*') ? 'active' : '' }}" href="{{ route('admin.sms-campaigns.index') }}"><span class="menu-icon">✉️</span><span class="menu-text">SMS campaigns</span></a>
                <a class="menu-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><span class="menu-icon">⚙️</span><span class="menu-text">Settings</span></a>
            @elseif(auth()->user()->isTeacher())
                <div class="app-sidebar-section">Teaching tools</div>
                <a class="menu-link {{ request()->routeIs('teacher.batches.*') ? 'active' : '' }}" href="{{ route('teacher.batches.index') }}"><span class="menu-icon">🗓️</span><span class="menu-text">My batches</span></a>
                <a class="menu-link {{ request()->routeIs('teacher.courses.*') ? 'active' : '' }}" href="{{ route('teacher.batches.index') }}"><span class="menu-icon">📚</span><span class="menu-text">My courses & lessons</span></a>
                <a class="menu-link {{ request()->routeIs('live-classes.*') ? 'active' : '' }}" href="{{ route('live-classes.index') }}"><span class="menu-icon">🎥</span><span class="menu-text">Live classes</span></a>
            @elseif(auth()->user()->isStudent())
                <div class="app-sidebar-section">My learning</div>
                <a class="menu-link {{ request()->routeIs('student.courses.*') ? 'active' : '' }}" href="{{ route('student.courses.index') }}"><span class="menu-icon">📚</span><span class="menu-text">My courses</span></a>
                <a class="menu-link {{ request()->routeIs('student.attendance.*') ? 'active' : '' }}" href="{{ route('student.attendance.index') }}"><span class="menu-icon">📅</span><span class="menu-text">Attendance</span></a>
                <a class="menu-link {{ request()->routeIs('student.exams.*') ? 'active' : '' }}" href="{{ route('student.exams.index') }}"><span class="menu-icon">📝</span><span class="menu-text">Exams & results</span></a>
                <a class="menu-link {{ request()->routeIs('student.invoices.*') ? 'active' : '' }}" href="{{ route('student.invoices.index') }}"><span class="menu-icon">🧾</span><span class="menu-text">My fees</span></a>
                <a class="menu-link {{ request()->routeIs('store.orders') ? 'active' : '' }}" href="{{ route('store.orders') }}"><span class="menu-icon">📦</span><span class="menu-text">My orders</span></a>
                <a class="menu-link {{ request()->routeIs('store.cart') ? 'active' : '' }}" href="{{ route('store.cart') }}"><span class="menu-icon">🛒</span><span class="menu-text">My cart</span></a>
                <a class="menu-link {{ request()->routeIs('live-classes.*') ? 'active' : '' }}" href="{{ route('live-classes.index') }}"><span class="menu-icon">🎥</span><span class="menu-text">Live classes</span></a>
            @elseif(auth()->user()->isGuardian())
                <div class="app-sidebar-section">Family workspace</div>
                <a class="menu-link {{ request()->routeIs('guardian.*') ? 'active' : '' }}" href="{{ route('guardian.dashboard') }}"><span class="menu-icon">👨‍👩‍👧</span><span class="menu-text">My children</span></a>
            @elseif(auth()->user()->isBranchManager())
                <div class="app-sidebar-section">Branch management</div>
                <a class="menu-link {{ request()->routeIs('branch-manager.batches.*') ? 'active' : '' }}" href="{{ route('branch-manager.batches.index') }}"><span class="menu-icon">🗓️</span><span class="menu-text">Branch batches</span></a>
                <a class="menu-link {{ request()->routeIs('branch-manager.students.*') ? 'active' : '' }}" href="{{ route('branch-manager.students.index') }}"><span class="menu-icon">👥</span><span class="menu-text">Branch students</span></a>
            @elseif(auth()->user()->isAccountant())
                <div class="app-sidebar-section">Finance workspace</div>
                <a class="menu-link {{ request()->routeIs('accountant.invoices.*') ? 'active' : '' }}" href="{{ route('accountant.invoices.index') }}"><span class="menu-icon">🧾</span><span class="menu-text">Invoices & fees</span></a>
                <a class="menu-link {{ request()->routeIs('accountant.refunds.*') ? 'active' : '' }}" href="{{ route('accountant.refunds.index') }}"><span class="menu-icon">↩️</span><span class="menu-text">Refunds</span></a>
                <a class="menu-link {{ request()->routeIs('accountant.expenses.*') ? 'active' : '' }}" href="{{ route('accountant.expenses.index') }}"><span class="menu-icon">💸</span><span class="menu-text">Expenses</span></a>
                <a class="menu-link {{ request()->routeIs('accountant.finance-reports.*') ? 'active' : '' }}" href="{{ route('accountant.finance-reports.index') }}"><span class="menu-icon">📊</span><span class="menu-text">Finance reports</span></a>
            @endif
            <div class="app-sidebar-section">Account</div>
            <a class="menu-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}"><span class="menu-icon">👤</span><span class="menu-text">My profile</span></a>
            <div class="app-sidebar-footer">Alom’s English Center<br>Learning Management System</div>
        </aside>
    @endauth
    <header class="topbar">
        <nav class="nav">
            @auth
                <button type="button" class="sidebar-toggle" aria-label="Open navigation menu" aria-controls="app-sidebar" aria-expanded="false" onclick="toggleAppSidebar()">☰</button>
            @endauth
            <a href="/{{ auth()->check() ? 'dashboard' : '' }}" class="brand">@if($websiteLogo)<img src="{{ $websiteLogo->url }}" alt="{{ $websiteSiteName }}" style="max-height:38px;max-width:150px;object-fit:contain">@else{{ auth()->check() ? config('app.name') : $websiteSiteName }}@endif</a>
            <div class="nav-links">
                <form method="POST" action="{{ route('locale.update') }}" class="inline-form" style="display:inline-flex;gap:6px;align-items:center;">
                    @csrf
                    <label for="locale-switch" class="muted" style="margin:0;">{{ __('Language') }}</label>
                    <select id="locale-switch" name="locale" onchange="this.form.submit()" style="width:auto;padding:8px 10px;">
                        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('English') }}</option>
                        <option value="bn" @selected(app()->getLocale() === 'bn')>{{ __('Bangla') }}</option>
                    </select>
                </form>
                @guest
                    <a href="/">{{ __('Home') }}</a>
                    <a href="{{ route('courses.index') }}">{{ __('Courses') }}</a>
                    @if(\Illuminate\Support\Facades\Schema::hasTable('flash_sales') && \App\Models\FlashSale::activeCampaigns()->isNotEmpty())<a href="{{ route('landing') }}#flash-sale">🔥 {{ __('Flash Sale') }}</a>@endif
                    <a href="{{ route('contests.index') }}">Quiz & prizes</a>
                    <a href="{{ route('pages.about') }}">{{ __('About') }}</a>
                    <a href="{{ route('pages.contact') }}">{{ __('Contact') }}</a>
                    <a href="{{ route('pages.faq') }}">{{ __('FAQ') }}</a>
                    <a href="{{ route('news.index') }}">{{ __('Blog & News') }}</a>
                    @foreach($websiteHeaderLinks as $websiteLink)<a href="{{ $websiteLink['url'] }}">{{ $websiteLink['label'] }}</a>@endforeach
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}">{{ __('Register') }}</a>
                @endguest
                @auth
                    <a href="{{ route('profile.edit') }}">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button type="submit">Logout</button></form>
                @endauth
            </div>
        </nav>
    </header>

    <main class="container">
        @if(session('success'))
            <div class="flash success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="flash info">{{ session('info') }}</div>
        @endif
        @if(session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
    @guest
        <footer class="container" style="padding-top:24px;padding-bottom:36px;border-top:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                <div style="max-width:620px;"><strong>{{ $websiteFooterTitle }}</strong>@if($websiteFooterText !== '')<p class="muted" style="margin:8px 0 0;line-height:1.7;">{{ $websiteFooterText }}</p>@endif</div>
                <nav aria-label="{{ __('Footer navigation') }}" style="display:flex;gap:12px;flex-wrap:wrap;">@foreach($websiteFooterLinks as $websiteLink)<a href="{{ $websiteLink['url'] }}">{{ $websiteLink['label'] }}</a>@endforeach</nav>
            </div>
        </footer>
    @endguest
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
        function toggleAppSidebar(force) {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const button = document.querySelector('.sidebar-toggle');
            if (!sidebar || !overlay) return;
            const open = typeof force === 'boolean' ? force : !sidebar.classList.contains('is-open');
            sidebar.classList.toggle('is-open', open);
            overlay.classList.toggle('is-visible', open);
            if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open && window.matchMedia('(max-width: 600px)').matches ? 'hidden' : '';
        }
        function saveMarketingConsent(choice) {
            const secure = window.location.protocol === 'https:' ? '; Secure' : '';
            const value = choice ? `marketing_consent=${choice}; Max-Age=15552000` : 'marketing_consent=; Max-Age=0';
            document.cookie = `${value}; Path=/; SameSite=Lax${secure}`;
            window.location.reload();
        }
    </script>
    @stack('scripts')
</body>
</html>
