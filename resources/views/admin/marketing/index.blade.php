@extends('layouts.app')

@section('title', __('Marketing & Boost Center') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Marketing & Boost Center') }}</h1>
            <p class="muted">{{ __('Manage tracking configuration, check search indexing endpoints and open your advertising platforms.') }}</p>
        </div>
        <a class="btn primary" href="{{ route('admin.settings.index') }}">{{ __('Configure integrations') }}</a>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px;">
        @foreach($integrations as $integration)
            <section class="panel">
                <div class="page-header" style="margin-bottom:8px;">
                    <h2 style="margin:0;">{{ $integration['label'] }}</h2>
                    <span class="badge {{ $integration['configured'] ? 'success' : 'warning' }}">{{ $integration['configured'] ? __('Configured') : __('Needs setup') }}</span>
                </div>
                <p class="muted" style="margin:0;">{{ $integration['detail'] }}</p>
            </section>
        @endforeach
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Search engine readiness') }}</h2>
        <div class="grid grid-2">
            <div>
                <label>{{ __('XML sitemap') }}</label>
                <input type="text" readonly value="{{ $sitemapUrl }}">
                <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener">{{ __('Open sitemap') }}</a>
            </div>
            <div>
                <label>{{ __('Robots.txt') }}</label>
                <input type="text" readonly value="{{ $robotsUrl }}">
                <a href="{{ $robotsUrl }}" target="_blank" rel="noopener">{{ __('Open robots.txt') }}</a>
            </div>
        </div>
        <p class="muted">{{ __('After the public website is deployed with its real domain, submit the sitemap URL in Google Search Console. Search Console verification must be completed for the same domain property.') }}</p>
    </section>

    <section class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Gmail / email verification delivery') }}</h2>
        <p class="muted">{{ __('Email verification links use Laravel mail delivery. For Gmail, configure SMTP on the server; keep the Google App Password in environment secrets, never in the LMS settings database.') }}</p>
        <pre style="white-space:pre-wrap;background:#f5f7fb;padding:14px;border-radius:10px;">MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=your-google-workspace-address
MAIL_PASSWORD=your-google-app-password
MAIL_FROM_ADDRESS=your-google-workspace-address
MAIL_FROM_NAME="Your Academy"</pre>
        <small class="muted">{{ __('Use a Google App Password with 2-Step Verification, then clear the application config cache after updating server environment variables. Do not paste credentials into this page.') }}</small>
    </section>

    <section class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Advertising platforms') }}</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn primary" href="{{ route('admin.marketing.boost-campaigns.index') }}">{{ __('Meta Boost Planner') }}</a>
            <a class="btn primary" href="{{ route('admin.marketing.meta-ads.index') }}">{{ __('Connect Meta Ads to LMS') }}</a>
            <a class="btn secondary" href="https://adsmanager.facebook.com/adsmanager/manage/campaigns" target="_blank" rel="noopener">{{ __('Open Meta Ads Manager') }}</a>
            <a class="btn secondary" href="https://business.facebook.com/" target="_blank" rel="noopener">{{ __('Open Meta Business Suite') }}</a>
            <a class="btn secondary" href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener">{{ __('Open Google Analytics') }}</a>
            <a class="btn secondary" href="https://search.google.com/search-console" target="_blank" rel="noopener">{{ __('Open Search Console') }}</a>
        </div>
        <p class="muted" style="margin-top:14px;">{{ __('The LMS currently supports consent-gated Meta Pixel tracking and platform shortcuts. Creating or activating new paid Meta Ads campaigns through this LMS requires a Meta Business app, approved Marketing API access, an ad account and authorized credentials. The connected Meta Ads tool can read reports, pause campaigns and resume a paused campaign only after an explicit spend-warning confirmation; it does not create new ads.') }}</p>
    </section>

    <section class="panel">
        <h2 style="margin-top:0;">{{ __('Tracked Meta Pixel events') }}</h2>
        <ul>
            <li>{{ __('PageView — public pages when marketing cookies are accepted.') }}</li>
            <li>{{ __('ViewContent — course detail pages when marketing cookies are accepted.') }}</li>
            <li>{{ __('CompleteRegistration — after a student account is successfully created, when marketing cookies are accepted.') }}</li>
        </ul>
        <p class="muted">{{ __('Pixel events are not sent before marketing consent. Configure IDs in Admin Settings → SEO and tracking.') }}</p>
    </section>
@endsection
