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

    <section class="panel" style="margin-bottom:20px;">
        <div class="page-header">
            <div>
                <h2 style="margin:0;">{{ __('Campaign performance snapshot') }}</h2>
                <p class="muted" style="margin:6px 0 0;">{{ __('Live totals from existing LMS campaign, support and announcement records. These are not external ad-platform analytics.') }}</p>
            </div>
            <a class="btn secondary" href="{{ route('admin.dashboard-announcements.index') }}">{{ __('Manage in-app announcements') }}</a>
        </div>
        <div class="grid grid-4">
            <article class="panel">
                <span class="muted">{{ __('SMS campaigns') }}</span>
                <strong style="display:block;font-size:1.8rem;">{{ number_format($broadcastMetrics['sms_campaigns']) }}</strong>
                <small class="muted">{{ number_format($broadcastMetrics['sms_sent']) }} {{ __('sent') }} · {{ number_format($broadcastMetrics['sms_failed']) }} {{ __('failed') }}</small>
            </article>
            <article class="panel">
                <span class="muted">{{ __('Meta boost plans') }}</span>
                <strong style="display:block;font-size:1.8rem;">{{ number_format($broadcastMetrics['meta_plans']) }}</strong>
                <small class="muted">{{ number_format($broadcastMetrics['meta_drafts']) }} {{ __('drafts') }}</small>
            </article>
            <article class="panel">
                <span class="muted">{{ __('Reported Meta spend') }}</span>
                <strong style="display:block;font-size:1.8rem;">৳{{ number_format($broadcastMetrics['meta_spend_bdt'], 2) }}</strong>
                <small class="muted">{{ __('Manually reported; not live synced spend') }}</small>
            </article>
            <article class="panel">
                <span class="muted">{{ __('Open support tickets') }}</span>
                <strong style="display:block;font-size:1.8rem;">{{ number_format($broadcastMetrics['open_support_tickets']) }}</strong>
                <small class="muted">{{ number_format($broadcastMetrics['active_announcements']) }} {{ __('active in-app announcements') }}</small>
            </article>
        </div>
    </section>

    <section class="panel" style="margin-bottom:20px;">
        <div class="page-header" style="margin-bottom:12px;">
            <div>
                <h2 style="margin:0;">{{ __('Broadcasting, Marketing & Automation') }}</h2>
                <p class="muted" style="margin:6px 0 0;">{{ __('One place to manage learner communications, campaigns, engagement and future channel integrations.') }}</p>
            </div>
            <span class="badge info">{{ __('Alom’s English Center') }}</span>
        </div>
        <div class="grid grid-3">
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('SMS Broadcast') }}</h3>
                <p class="muted">{{ __('Create queued SMS campaigns for verified learners who have explicitly opted in. Delivery status and recipient reports are available.') }}</p>
                <span class="badge success">{{ __('Available') }}</span>
                <div style="margin-top:12px;"><a class="btn primary" href="{{ route('admin.sms-campaigns.index') }}">{{ __('Open SMS Campaigns') }}</a></div>
            </article>
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('Meta Campaigns') }}</h3>
                <p class="muted">{{ __('Plan and review Meta promotion activity. Live ad creation and publishing remain dependent on approved platform access.') }}</p>
                <span class="badge success">{{ __('Planner available') }}</span>
                <div style="margin-top:12px;"><a class="btn primary" href="{{ route('admin.marketing.boost-campaigns.index') }}">{{ __('Open Campaign Planner') }}</a></div>
            </article>
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('Support Inbox') }}</h3>
                <p class="muted">{{ __('Review and respond to learner support tickets from the existing support workflow.') }}</p>
                <span class="badge success">{{ __('Available') }}</span>
                <div style="margin-top:12px;"><a class="btn primary" href="{{ route('admin.support-tickets.index') }}">{{ __('Open Support Inbox') }}</a></div>
            </article>
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('Email & Notices') }}</h3>
                <p class="muted">{{ __('Use configured transactional email and the LMS notice board for operational communication. Bulk email marketing needs a dedicated consent-aware campaign queue.') }}</p>
                <span class="badge warning">{{ __('Partially available') }}</span>
            </article>
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('Omnichannel Inbox') }}</h3>
                <p class="muted">{{ __('A unified conversation timeline for WhatsApp, Messenger, Instagram, Telegram, website chat and email, with assignment and hand-off to staff.') }}</p>
                <span class="badge warning">{{ __('Planned — not connected') }}</span>
            </article>
            <article class="panel" style="background:var(--surface-muted, #f7f8fc);">
                <h3 style="margin-top:0;">{{ __('Workflow Automation & APIs') }}</h3>
                <p class="muted">{{ __('Event-triggered journeys, approved webhooks, API keys, delivery retries and campaign analytics with human review for sensitive actions.') }}</p>
                <span class="badge warning">{{ __('Planned') }}</span>
            </article>
        </div>
        <p class="muted" style="margin:14px 0 0;">{{ __('Channel integrations are never shown as connected until credentials, webhook verification, consent checks and a successful health check are completed. Do not paste provider secrets into campaign messages or public settings.') }}</p>
    </section>

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
