<?php

namespace App\Http\Controllers;

use App\Models\DashboardAnnouncement;
use App\Models\MetaBoostCampaign;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\SupportTicket;
use App\Services\Integrations\ApiConnectorManager;
use Illuminate\View\View;

class MarketingCenterController extends Controller
{
    public function index(ApiConnectorManager $connectorManager): View
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'facebook_pixel_id',
                'google_analytics_id',
                'google_tag_manager_id',
                'google_search_console_verification',
            ])
            ->pluck('value', 'key');

        $mailMailer = (string) config('mail.default');
        $mailHost = (string) config('mail.mailers.smtp.host');
        $mailReady = $mailMailer === 'smtp'
            && filled($mailHost)
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && filled(config('mail.from.address'));

        $integrations = [
            'meta_pixel' => [
                'label' => 'Meta Pixel',
                'configured' => filled($settings->get('facebook_pixel_id')),
                'detail' => $settings->get('facebook_pixel_id') ?: 'Not configured',
            ],
            'google_analytics' => [
                'label' => 'Google Analytics 4',
                'configured' => filled($settings->get('google_analytics_id')),
                'detail' => $settings->get('google_analytics_id') ?: 'Not configured',
            ],
            'tag_manager' => [
                'label' => 'Google Tag Manager',
                'configured' => filled($settings->get('google_tag_manager_id')),
                'detail' => $settings->get('google_tag_manager_id') ?: 'Not configured',
            ],
            'search_console' => [
                'label' => 'Google Search Console',
                'configured' => filled($settings->get('google_search_console_verification')),
                'detail' => filled($settings->get('google_search_console_verification')) ? 'Verification token saved' : 'Not verified',
            ],
            'email_delivery' => [
                'label' => 'Email verification delivery',
                'configured' => $mailReady,
                'detail' => $mailReady
                    ? ($mailHost === 'smtp.gmail.com' ? 'Gmail SMTP configured' : 'SMTP configured: '.$mailHost)
                    : 'SMTP credentials are not configured in the server environment',
            ],
        ];

        $broadcastMetrics = [
            'sms_campaigns' => SmsCampaign::count(),
            'sms_sent' => (int) SmsCampaign::sum('sent_count'),
            'sms_failed' => (int) SmsCampaign::sum('failed_count'),
            'meta_plans' => MetaBoostCampaign::count(),
            'meta_drafts' => MetaBoostCampaign::where('status', 'draft')->count(),
            'meta_spend_bdt' => (float) MetaBoostCampaign::sum('actual_spend_bdt'),
            'open_support_tickets' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'active_announcements' => DashboardAnnouncement::currentlyActive()->count(),
        ];

        return view('admin.marketing.index', [
            'integrations' => $integrations,
            'broadcastMetrics' => $broadcastMetrics,
            'apiConnectors' => $connectorManager->catalog(),
            'sitemapUrl' => route('seo.sitemap'),
            'robotsUrl' => route('seo.robots'),
        ]);
    }
}
