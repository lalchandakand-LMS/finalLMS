@extends('layouts.app')

@section('title', __('Meta Boost Planner') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Admin') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Meta Boost Planner') }}</h1>
            <p class="muted">{{ __('Prepare Facebook/Instagram campaign briefs, build UTM tracking links and record manually reported results.') }}</p>
        </div>
        <a class="btn primary" href="https://adsmanager.facebook.com/adsmanager/manage/campaigns" target="_blank" rel="noopener">{{ __('Open Meta Ads Manager') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">{{ __('Create boost plan') }}</h2>
        <p class="muted">{{ __('Saving a plan does not publish an advertisement or spend money. Use the prepared brief in Meta Ads Manager, then record its campaign ID and results here.') }}</p>
        <form method="POST" action="{{ route('admin.marketing.boost-campaigns.store') }}">
            @csrf
            <div class="grid grid-2">
                <label>{{ __('Campaign name') }}<input name="title" maxlength="180" value="{{ old('title') }}" required></label>
                <label>{{ __('Objective') }}<select name="objective" required>@foreach(['awareness' => 'Awareness', 'traffic' => 'Traffic', 'engagement' => 'Engagement', 'leads' => 'Leads', 'sales' => 'Sales'] as $value => $label)<option value="{{ $value }}" @selected(old('objective', 'traffic') === $value)>{{ __($label) }}</option>@endforeach</select></label>
                <label>{{ __('Facebook post URL (optional)') }}<input type="url" name="post_url" maxlength="2048" value="{{ old('post_url') }}" placeholder="https://www.facebook.com/..."></label>
                <label>{{ __('Landing page URL') }}<input type="url" name="destination_url" maxlength="2048" value="{{ old('destination_url') }}" required placeholder="https://your-domain.com/course"></label>
                <label>{{ __('Target location') }}<input name="target_location" maxlength="160" value="{{ old('target_location') }}" placeholder="{{ __('Example: Rangpur, Bangladesh') }}"></label>
                <label>{{ __('Daily budget (BDT)') }}<input type="number" name="daily_budget_bdt" min="1" step="0.01" value="{{ old('daily_budget_bdt') }}" required></label>
                <label>{{ __('Total planned budget (BDT)') }}<input type="number" name="total_budget_bdt" min="1" step="0.01" value="{{ old('total_budget_bdt') }}"></label>
                <label>{{ __('Start date/time') }}<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label>
                <label>{{ __('End date/time') }}<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"></label>
            </div>
            <label>{{ __('Target audience description') }}<textarea name="audience_description" rows="3" maxlength="2000">{{ old('audience_description') }}</textarea></label>
            <label>{{ __('Notes') }}<textarea name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea></label>
            <button class="btn primary" type="submit">{{ __('Save draft plan') }}</button>
        </form>
    </div>

    <h2>{{ __('Boost plans and campaign reports') }}</h2>
    <div class="course-list">
        @forelse($campaigns as $campaign)
            <article class="panel" style="margin-bottom:16px;">
                <div class="page-header" style="margin-bottom:10px;">
                    <div>
                        <span class="badge {{ $campaign->status === 'running' ? 'success' : 'warning' }}">{{ __(ucfirst($campaign->status)) }}</span>
                        <h3 style="margin:8px 0 4px;">{{ $campaign->title }}</h3>
                        <small class="muted">{{ __(ucfirst($campaign->objective)) }} · {{ $campaign->target_location ?: __('Location not specified') }}</small>
                    </div>
                    <strong>{{ __('BDT') }} {{ number_format((float) $campaign->daily_budget_bdt, 2) }} / {{ __('day') }}</strong>
                </div>

                @if($campaign->audience_description)<p>{{ $campaign->audience_description }}</p>@endif
                <label>{{ __('Landing page with UTM tracking') }}<input type="text" readonly value="{{ $campaign->tracking_url }}"></label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 16px;">
                    <a class="btn secondary" href="{{ $campaign->tracking_url }}" target="_blank" rel="noopener">{{ __('Open tracked landing page') }}</a>
                    @if($campaign->post_url)<a class="btn secondary" href="{{ $campaign->post_url }}" target="_blank" rel="noopener">{{ __('Open Facebook post') }}</a>@endif
                    <a class="btn primary" href="https://adsmanager.facebook.com/adsmanager/manage/campaigns" target="_blank" rel="noopener">{{ __('Open Ads Manager') }}</a>
                </div>

                <div class="grid grid-4" style="margin-bottom:14px;">
                    <div><small class="muted">{{ __('Actual spend') }}</small><strong>{{ number_format((float) $campaign->actual_spend_bdt, 2) }} BDT</strong></div>
                    <div><small class="muted">{{ __('Impressions') }}</small><strong>{{ number_format($campaign->impressions) }}</strong></div>
                    <div><small class="muted">{{ __('Clicks') }}</small><strong>{{ number_format($campaign->clicks) }}</strong></div>
                    <div><small class="muted">{{ __('Leads / conversions') }}</small><strong>{{ number_format($campaign->leads) }} / {{ number_format($campaign->conversions) }}</strong></div>
                </div>

                <details>
                    <summary style="cursor:pointer;font-weight:700;">{{ __('Update campaign status and manually reported results') }}</summary>
                    <form method="POST" action="{{ route('admin.marketing.boost-campaigns.update', $campaign) }}" style="margin-top:14px;">
                        @csrf @method('PUT')
                        <div class="grid grid-3">
                            <label>{{ __('Status') }}<select name="status">@foreach(['draft', 'planned', 'running', 'paused', 'completed'] as $status)<option value="{{ $status }}" @selected($campaign->status === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label>
                            <label>{{ __('Meta campaign ID') }}<input name="meta_campaign_id" maxlength="120" value="{{ $campaign->meta_campaign_id }}"></label>
                            <label>{{ __('Actual spend (BDT)') }}<input type="number" name="actual_spend_bdt" min="0" step="0.01" value="{{ $campaign->actual_spend_bdt }}" required></label>
                            <label>{{ __('Impressions') }}<input type="number" name="impressions" min="0" value="{{ $campaign->impressions }}" required></label>
                            <label>{{ __('Clicks') }}<input type="number" name="clicks" min="0" value="{{ $campaign->clicks }}" required></label>
                            <label>{{ __('Leads') }}<input type="number" name="leads" min="0" value="{{ $campaign->leads }}" required></label>
                            <label>{{ __('Conversions') }}<input type="number" name="conversions" min="0" value="{{ $campaign->conversions }}" required></label>
                        </div>
                        <label>{{ __('Notes') }}<textarea name="notes" rows="2" maxlength="5000">{{ $campaign->notes }}</textarea></label>
                        <button class="btn primary" type="submit">{{ __('Save report') }}</button>
                    </form>
                </details>

                @if($campaign->status === 'draft')
                    <form method="POST" action="{{ route('admin.marketing.boost-campaigns.destroy', $campaign) }}" style="margin-top:12px;" onsubmit="return confirm('{{ __('Delete this draft plan?') }}')">
                        @csrf @method('DELETE')
                        <button class="btn secondary" type="submit">{{ __('Delete draft') }}</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted">{{ __('No boost plans created yet.') }}</p></div>
        @endforelse
    </div>
    <div style="margin-top:18px;">{{ $campaigns->links() }}</div>
@endsection
