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
    <style>
        :root {
            --bg: #f3f6fb;
            --panel: #ffffff;
            --panel-alt: #eef4ff;
            --primary: #1546a8;
            --primary-dark: #0f2d6b;
            --green: #1f9d61;
            --text: #18212f;
            --muted: #5b6576;
            --border: #dfe7f6;
            --danger: #b42318;
            --shadow: 0 14px 34px rgba(21, 70, 168, 0.12);
        }
        * { box-sizing: border-box; }
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
<body>
    @if($marketingConsent && $googleTagManagerId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($googleTagManagerId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    <header class="topbar">
        <nav class="nav">
            <a href="/{{ auth()->check() ? 'dashboard' : '' }}" class="brand">{{ config('app.name') }}</a>
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
                <a href="{{ route('pages.about') }}">{{ __('About') }}</a>
                <a href="{{ route('pages.contact') }}">{{ __('Contact') }}</a>
                <a href="{{ route('pages.faq') }}">{{ __('FAQ') }}</a>
                <a href="{{ route('news.index') }}">{{ __('Blog & News') }}</a>
                @auth
                    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                    <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                    <a href="{{ route('live-classes.index') }}">{{ __('Live classes') }}</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.batches.index') }}">Batches</a>
                        <a href="{{ route('admin.sms-campaigns.index') }}">SMS</a>
                        <a href="{{ route('admin.payments.index') }}">Payments</a>
                        <a href="{{ route('admin.invoices.index') }}">Invoices</a>
                        <a href="{{ route('admin.activity-logs.index') }}">Activity log</a>
                        <a href="{{ route('admin.settings.index') }}">Settings</a>
                        <a href="{{ route('admin.website-builder.index') }}">Website Builder</a>
                        <a href="{{ route('admin.media.index') }}">Media Library</a>
                        <a href="{{ route('admin.news.index') }}">Blog & News</a>
                        <a href="{{ route('admin.users.index') }}">Users</a>
                        <a href="{{ route('admin.courses.index') }}">Courses</a>
                    @endif
                    @if(auth()->user()->isBranchManager())
                        <a href="{{ route('branch-manager.batches.index') }}">My branch</a>
                    @endif
                    @if(auth()->user()->isAccountant())
                        <a href="{{ route('accountant.invoices.index') }}">Finance</a>
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
    @stack('scripts')
</body>
</html>
