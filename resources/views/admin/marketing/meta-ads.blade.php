@extends('layouts.app')

@section('title', __('Meta Ads Integration') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin only') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Facebook & Instagram Ads') }}</h1>
            <p class="muted">{{ __('Connect your Meta business assets, inspect campaign performance, pause campaigns and resume them after a spend warning.') }}</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.marketing.index') }}">{{ __('Marketing center') }}</a>
    </div>

    @if($error)
        <div class="flash error">{{ $error }}</div>
    @endif

    @unless($configured)
        <div class="panel">
            <h2>{{ __('Meta app setup required') }}</h2>
            <p>{{ __('Add META_ADS_APP_ID, META_ADS_APP_SECRET and META_ADS_REDIRECT_URI to the server environment. Never paste secrets into the LMS form or source code.') }}</p>
            <p class="muted">{{ __('The redirect URI must exactly match the callback URL shown below in your Meta Developer app.') }}</p>
            <code>{{ route('admin.marketing.meta-ads.callback') }}</code>
        </div>
    @endunless

    @if(!$connected)
        <div class="panel">
            <h2>{{ __('Connect a Meta account') }}</h2>
            <p>{{ __('Authorize the LMS to read ad accounts, pages and campaign insights, and to pause campaigns. The LMS does not create, activate or spend money on ads.') }}</p>
            <p class="muted">{{ __('Requested permissions: ads_read, ads_management, business_management, pages_read_engagement and pages_show_list.') }}</p>
            <a class="btn primary {{ !$configured ? 'disabled' : '' }}" href="{{ $configured ? route('admin.marketing.meta-ads.connect') : '#' }}" @if(!$configured) aria-disabled="true" onclick="return false;" @endif>{{ __('Connect Facebook / Meta') }}</a>
        </div>
    @else
        <div class="panel" style="margin-bottom:18px;">
            <div class="page-header">
                <div>
                    <span class="badge success">{{ __('Connected') }}</span>
                    <h2 style="margin:8px 0 0;">{{ __('Meta business assets') }}</h2>
                    <small class="muted">{{ __('Token expiry') }}: {{ $expiresAt ?: __('Not provided by Meta') }}</small>
                </div>
                <form method="POST" action="{{ route('admin.marketing.meta-ads.disconnect') }}" onsubmit="return confirm('{{ __('Disconnect Meta Ads from this LMS?') }}')">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('Disconnect') }}</button>
                </form>
            </div>
            <div class="grid grid-2">
                <div>
                    <h3>{{ __('Ad accounts') }}</h3>
                    @if(count($accounts))
                        <form method="GET" action="{{ route('admin.marketing.meta-ads.index') }}">
                            <label>{{ __('Select ad account') }}
                                <select name="account" onchange="this.form.submit()">
                                    <option value="">{{ __('Choose an ad account') }}</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account['account_id'] }}" @selected($selectedAccount === (string) $account['account_id'])>{{ $account['name'] ?? $account['account_id'] }} ({{ $account['currency'] ?? '—' }})</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                    @else
                        <p class="muted">{{ __('No ad accounts were returned. Check business access and granted permissions.') }}</p>
                    @endif
                </div>
                <div>
                    <h3>{{ __('Facebook Pages') }}</h3>
                    @forelse($pages as $page)
                        <p style="margin:6px 0;">{{ $page['name'] ?? $page['id'] }} <small class="muted">({{ $page['id'] }})</small></p>
                    @empty
                        <p class="muted">{{ __('No Pages were returned for this Meta user.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>


        @if($selectedAccount && count($pages))
            <div class="panel" style="margin:18px 0;">
                <h2>{{ __('Create a Facebook / Instagram boost') }}</h2>
                <p class="muted">{{ __('Build a traffic campaign for an LMS course, admission offer or notice. Every Meta object is created as PAUSED. No ad will deliver or spend until an administrator separately confirms Resume.') }}</p>
                <form method="POST" action="{{ route('admin.marketing.meta-ads.campaigns.store') }}" onsubmit="return confirm('{{ __('Create this campaign, ad set, creative and ad in PAUSED mode? No spend will occur until you separately resume it.') }}')">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $selectedAccount }}">
                    <div class="grid grid-2">
                        <label>{{ __('Campaign name') }}
                            <input name="name" maxlength="180" required value="{{ old('name') }}" placeholder="{{ __('e.g. HSC English Course - November') }}">
                        </label>
                        <label>{{ __('Facebook Page') }}
                            <select name="page_id" required>
                                <option value="">{{ __('Choose a Page') }}</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page['id'] }}" @selected(old('page_id') === (string) $page['id'])>{{ $page['name'] ?? $page['id'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>{{ __('Course / offer landing page URL') }}
                            <input type="url" name="destination_url" required maxlength="2048" value="{{ old('destination_url') }}" placeholder="https://your-lms-domain.example/courses/...">
                        </label>
                        <label>{{ __('Public image URL (HTTPS recommended)') }}
                            <input type="url" name="image_url" required maxlength="2048" value="{{ old('image_url') }}" placeholder="https://your-domain.example/storage/course-banner.jpg">
                        </label>
                    </div>
                    <label>{{ __('Ad message') }}
                        <textarea name="message" rows="3" maxlength="2000" required>{{ old('message') }}</textarea>
                    </label>
                    <div class="grid grid-3">
                        <label>{{ __('Daily budget (BDT)') }}
                            <input type="number" name="daily_budget_bdt" min="100" max="1000000" step="1" required value="{{ old('daily_budget_bdt', 300) }}">
                        </label>
                        <label>{{ __('Minimum age') }}
                            <input type="number" name="age_min" min="18" max="65" required value="{{ old('age_min', 18) }}">
                        </label>
                        <label>{{ __('Maximum age') }}
                            <input type="number" name="age_max" min="18" max="65" required value="{{ old('age_max', 45) }}">
                        </label>
                    </div>
                    <p class="muted">{{ __('Target location: Bangladesh. Placements: Facebook Feed and Instagram Feed. Objective: Website traffic. The selected ad account must use BDT currency. Meta may apply a higher minimum budget or reject assets that are not approved.') }}</p>
                    <button class="btn primary" type="submit">{{ __('Create paused campaign') }}</button>
                </form>
            </div>
        @endif

        @if($selectedAccount)
            <h2>{{ __('Last 30 days performance') }}</h2>
            <div class="grid grid-4" style="margin-bottom:18px;">
                @foreach([
                    __('Spend') => ($insights['spend'] ?? '0').' '.(collect($accounts)->firstWhere('account_id', $selectedAccount)['currency'] ?? 'currency'),
                    __('Impressions') => number_format((int) ($insights['impressions'] ?? 0)),
                    __('Clicks') => number_format((int) ($insights['clicks'] ?? 0)),
                    __('Reach') => number_format((int) ($insights['reach'] ?? 0)),
                ] as $label => $value)
                    <div class="panel"><small class="muted">{{ $label }}</small><h2 style="margin:8px 0 0;">{{ $value }}</h2></div>
                @endforeach
            </div>

            <h2>{{ __('Campaigns') }}</h2>
            <div class="course-list">
                @forelse($campaigns as $campaign)
                    <article class="panel" style="margin-bottom:12px;">
                        <div class="page-header">
                            <div>
                                <strong>{{ $campaign['name'] ?? $campaign['id'] }}</strong>
                                <p class="muted" style="margin:5px 0;">{{ $campaign['objective'] ?? '—' }} · {{ $campaign['effective_status'] ?? $campaign['status'] ?? '—' }}</p>
                                <small class="muted">{{ __('Campaign ID') }}: {{ $campaign['id'] }}</small>
                            </div>
                            @if(($campaign['status'] ?? '') === 'PAUSED')
                                <form method="POST" action="{{ route('admin.marketing.meta-ads.campaigns.resume', $campaign['id']) }}" onsubmit="return confirm('{{ __('Resume this campaign? It may start delivering ads and spend its configured budget. Continue only if you approve this spend.') }}')">
                                    @csrf
                                    <input type="hidden" name="account_id" value="{{ $selectedAccount }}">
                                    <button class="btn primary" type="submit">{{ __('Resume campaign') }}</button>
                                </form>
                            @elseif(!in_array($campaign['effective_status'] ?? $campaign['status'] ?? '', ['PAUSED', 'ARCHIVED', 'DELETED'], true))
                                <form method="POST" action="{{ route('admin.marketing.meta-ads.campaigns.pause', $campaign['id']) }}" onsubmit="return confirm('{{ __('Pause this Meta campaign now? This will stop its delivery.') }}')">
                                    @csrf
                                    <input type="hidden" name="account_id" value="{{ $selectedAccount }}">
                                    <button class="btn secondary" type="submit">{{ __('Pause campaign') }}</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="panel"><p class="muted">{{ __('No campaigns found for this ad account.') }}</p></div>
                @endforelse
            </div>
        @endif
    @endif

    <div class="panel" style="margin-top:20px;">
        <h3>{{ __('Safety controls') }}</h3>
        <ul>
            <li>{{ __('Access tokens are encrypted before being stored in the settings table.') }}</li>
            <li>{{ __('New campaigns are created with campaign, ad set and ad all PAUSED. Resuming requires a separate confirmation.') }}</li>
            <li>{{ __('Creating a campaign never activates it. Only a separate explicit Resume action can start delivery and spend.') }}</li>
        </ul>
    </div>
@endsection
