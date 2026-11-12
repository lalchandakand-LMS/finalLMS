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
        $isPublicLayout = !(auth()->check() && auth()->user()->isAdmin());
        $websiteHeaderLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('header_links', '[]'), true) : [];
        $websiteFooterTitle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_title', config('app.name')) : config('app.name');
        $websiteFooterText = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('footer_text', '') : '';
        $websiteFooterLinks = $isPublicLayout && $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('footer_links', '[]'), true) : [];
        $themePrimary = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_primary_color', '#1546a8') : '#1546a8';
        $themeBackground = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_background_color', '#f3f6fb') : '#f3f6fb';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themePrimary)) $themePrimary = '#1546a8';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themeBackground)) $themeBackground = '#f3f6fb';
        $themeBackgroundStyle = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_background_style', 'soft-gradient') : 'soft-gradient';
        $themeBackgrounds = ['solid' => $themeBackground, 'soft-gradient' => 'radial-gradient(circle at top left, rgba(21,70,168,0.10), transparent 30%), linear-gradient(180deg, ' . $themeBackground . ' 0%, #ffffff 100%)', 'warm-gradient' => 'radial-gradient(circle at top left, rgba(180,130,70,0.10), transparent 30%), linear-gradient(180deg, ' . $themeBackground . ' 0%, #fffdf9 100%)'];
        $themePageBackground = $themeBackgrounds[$themeBackgroundStyle] ?? $themeBackgrounds['soft-gradient'];
        $themeFont = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_font_family', 'system') : 'system';
        $themeFonts = ['system' => 'Arial, Helvetica, sans-serif', 'inter' => 'Inter, Arial, sans-serif', 'nunito' => 'Nunito, Arial, sans-serif', 'poppins' => 'Poppins, Arial, sans-serif', 'georgia' => 'Georgia, serif'];
        $themeFontFamily = $themeFonts[$themeFont] ?? $themeFonts['system'];
        $themeHeadingScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_heading_scale', '1') : '1';
        $themeBodyScale = $isPublicLayout && $hasSettingsTable ? \App\Models\Setting::value('site_body_scale', '1') : '1';
        if (!in_array($themeHeadingScale, ['0.9', '1', '1.1', '1.2'], true)) $themeHeadingScale = '1';
        if (!in_array($themeBodyScale, ['0.9', '1', '1.05', '1.1'], true)) $themeBodyScale = '1';
    @endphp
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
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
    @php($isAdminLayout = auth()->check() && auth()->user()->isAdmin())
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
    </style>
</head>
<body class="{{ $isAdminLayout ? 'admin-shell' : '' }}">
    @if($marketingConsent && $googleTagManagerId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($googleTagManagerId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @if($isAdminLayout)
        <aside class="admin-sidebar" aria-label="Admin navigation">
            <a class="admin-sidebar-brand" href="{{ route('dashboard') }}"><span>📘</span><strong>Alom’s English Center<br><small style="font-weight:500;color:#a9bee0;">Admin workspace</small></strong></a>
            <div class="admin-sidebar-label">Overview</div>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span><span class="nav-text">Dashboard</span></a>
            <div class="admin-sidebar-label">Academy</div>
            <a href="{{ route('admin.courses.index') }}"><span>📚</span><span class="nav-text">Courses & lessons</span></a>
            <a href="{{ route('admin.flash-sales.index') }}"><span>🔥</span><span class="nav-text">Flash Sale</span></a>
            <a href="{{ route('admin.users.index') }}"><span>👥</span><span class="nav-text">Students & teachers</span></a>
            <a class="{{ request()->routeIs('admin.teacher-applications.*') ? 'active' : '' }}" href="{{ route('admin.teacher-applications.index') }}"><span>🪪</span><span class="nav-text">Teacher KYC review</span></a>
            <a class="{{ request()->routeIs('admin.student-devices.*') ? 'active' : '' }}" href="{{ route('admin.student-devices.index') }}"><span>📱</span><span class="nav-text">Device approvals</span></a>
            <a href="{{ route('admin.batches.index') }}"><span>🗓️</span><span class="nav-text">Batches & branches</span></a>
            <a href="{{ route('admin.contests.index') }}"><span>🎁</span><span class="nav-text">Quiz & lottery</span></a>
            <a href="{{ route('live-classes.index') }}"><span>🎥</span><span class="nav-text">Live classes</span></a>
            <div class="admin-sidebar-label">Finance</div>
            <a href="{{ route('admin.payments.index') }}"><span>💳</span><span class="nav-text">Payments</span></a>
            <a href="{{ route('admin.invoices.index') }}"><span>🧾</span><span class="nav-text">Invoices</span></a>
            <a href="{{ route('admin.refunds.index') }}"><span>↩️</span><span class="nav-text">Refunds</span></a>
            <a href="{{ route('admin.expenses.index') }}"><span>💸</span><span class="nav-text">Expenses & reports</span></a>
            <div class="admin-sidebar-label">Website & tools</div>
            <a href="{{ route('admin.website-builder.index') }}"><span>🖥️</span><span class="nav-text">Website builder</span></a>
            <a href="{{ route('admin.media.index') }}"><span>🖼️</span><span class="nav-text">Media library</span></a>
            <a href="{{ route('admin.news.index') }}"><span>📰</span><span class="nav-text">Blog & news</span></a>
            <a href="{{ route('admin.backups.index') }}"><span>💾</span><span class="nav-text">Backup & restore</span></a>
            <a href="{{ route('admin.activity-logs.index') }}"><span>📋</span><span class="nav-text">Activity logs</span></a>
            <a href="{{ route('admin.settings.index') }}"><span>⚙️</span><span class="nav-text">Settings</span></a>
            <a href="{{ route('admin.sms-campaigns.index') }}"><span>✉️</span><span class="nav-text">SMS campaigns</span></a>
            <div class="admin-sidebar-footer">Alom’s English Center<br>Learning Management System</div>
        </aside>
    @endif
    <header class="topbar">
        <nav class="nav">
            <a href="/{{ auth()->check() ? 'dashboard' : '' }}" class="brand">{{ config('app.name') }}</a>
            @if($isAdminLayout)
            <div class="nav-links">
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
                @if(\Illuminate\Support\Facades\Schema::hasTable('flash_sales') && \App\Models\FlashSale::activeCampaigns()->isNotEmpty())<a href="{{ route('landing') }}#flash-sale">🔥 {{ __('Flash Sale') }}</a>@endif
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
                    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                    <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                    <a href="{{ route('live-classes.index') }}">{{ __('Live classes') }}</a>
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
                        <a href="{{ route('admin.news.index') }}">{{ __('Blog & News') }}</a>
                        <a href="{{ route('admin.users.index') }}">Users</a>
                        <a href="{{ route('admin.courses.index') }}">Courses</a>
                        <a href="{{ route('admin.flash-sales.index') }}">Flash Sale</a>
                    @endif
                    @if(auth()->user()->isBranchManager())
                        <a href="{{ route('branch-manager.batches.index') }}">My branch</a>
                    @endif
                    @if(auth()->user()->isAccountant())
                        <a href="{{ route('accountant.invoices.index') }}">Finance</a>
                        <a href="{{ route('accountant.refunds.index') }}">Refunds</a>
                        <a href="{{ route('accountant.expenses.index') }}">Expenses</a>
                        <a href="{{ route('accountant.finance-reports.index') }}">Reports</a>
                    @endif
                    @if(auth()->user()->isTeacher())
                        <a href="{{ route('teacher.batches.index') }}">My batches</a>
                    @endif
                    @if(auth()->user()->isStudent())
                        <a href="{{ route('student.attendance.index') }}">Attendance</a>
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
    @stack('scripts')
</body>
</html>
