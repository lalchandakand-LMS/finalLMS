<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class MarketingCenterController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'facebook_pixel_id',
                'google_analytics_id',
                'google_tag_manager_id',
                'google_search_console_verification',
            ])
            ->pluck('value', 'key');

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
        ];

        return view('admin.marketing.index', [
            'integrations' => $integrations,
            'sitemapUrl' => route('seo.sitemap'),
            'robotsUrl' => route('seo.robots'),
        ]);
    }
}
