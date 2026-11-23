<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()->pluck('value', 'key')->toArray();
        $paymentMethods = Setting::paymentMethods();
        $mediaAssets = MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->get();

        return view('admin.settings.index', compact('settings', 'paymentMethods', 'mediaAssets'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_name' => ['sometimes', 'required', 'string', 'max:160'],
            'institution_short_name' => ['nullable', 'string', 'max:60'],
            'institution_tagline' => ['nullable', 'string', 'max:180'],
            'institution_contact_email' => ['nullable', 'email', 'max:190'],
            'institution_contact_phone' => ['nullable', 'string', 'max:40'],
            'institution_address' => ['nullable', 'string', 'max:500'],
            'institution_website' => ['nullable', 'url', 'max:255'],
            'institution_logo_url' => ['nullable', 'url', 'max:500'],
            'institution_favicon_url' => ['nullable', 'url', 'max:500'],
            'site_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'site_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_surface_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dashboard_hero_image_media_id' => ['nullable', 'integer', 'min:1'],
            'dashboard_live_class_image_media_id' => ['nullable', 'integer', 'min:1'],
            'dashboard_hero_title' => ['nullable', 'string', 'max:180'],
            'dashboard_hero_subtitle' => ['nullable', 'string', 'max:300'],
            'dashboard_hero_quote' => ['nullable', 'string', 'max:180'],
            'institution_default_locale' => ['sometimes', 'required', 'in:en,bn'],
            'institution_timezone' => ['sometimes', 'required', 'timezone'],
            'site_logo_media_id' => ['nullable', 'integer', 'min:1'],
            'site_favicon_media_id' => ['nullable', 'integer', 'min:1'],
            'loading_screen_enabled' => ['nullable', 'boolean'],
            'loading_screen_logo_media_id' => ['nullable', 'integer', 'min:1'],
            'loading_screen_style' => ['nullable', 'in:orbit,pulse,ring'],
            'loading_screen_text' => ['nullable', 'string', 'max:60'],
            'loading_screen_background' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'loading_screen_speed' => ['nullable', 'integer', 'min:6', 'max:24'],
            'home_title' => ['nullable', 'string', 'max:255'],
            'home_subtitle' => ['nullable', 'string', 'max:500'],
            'home_cta_primary' => ['nullable', 'string', 'max:50'],
            'home_cta_secondary' => ['nullable', 'string', 'max:50'],
            'home_featured_title' => ['nullable', 'string', 'max:255'],
            'home_reason_title' => ['nullable', 'string', 'max:255'],
            'home_metric_1_value' => ['nullable', 'string', 'max:50'],
            'home_metric_1_label' => ['nullable', 'string', 'max:50'],
            'home_metric_2_value' => ['nullable', 'string', 'max:50'],
            'home_metric_2_label' => ['nullable', 'string', 'max:50'],
            'home_metric_3_value' => ['nullable', 'string', 'max:50'],
            'home_metric_3_label' => ['nullable', 'string', 'max:50'],
            'flash_sale_page_enabled' => ['nullable', 'boolean'],
            'home_slider_autoplay' => ['nullable', 'boolean'],
            'home_slider_interval' => ['nullable', 'integer', 'min:3000', 'max:12000'],
            'home_slider_arrows' => ['nullable', 'boolean'],
            'home_slider_dots' => ['nullable', 'boolean'],
            'home_slider_pause_hover' => ['nullable', 'boolean'],
            'home_slider_height' => ['nullable', 'integer', 'min:320', 'max:720'],
            'home_slider_mobile_height' => ['nullable', 'integer', 'min:220', 'max:520'],
            'home_slider_overlay' => ['nullable', 'integer', 'min:20', 'max:90'],
            'home_batches_title' => ['nullable', 'string', 'max:120'],
            'home_stats_title' => ['nullable', 'string', 'max:120'],
            'home_paths_title' => ['nullable', 'string', 'max:120'],
            'home_reviews_title' => ['nullable', 'string', 'max:120'],
            'home_slides' => ['nullable', 'array', 'max:3'],
            'home_slides.*.enabled' => ['nullable', 'boolean'],
            'home_slides.*.image_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'home_slides.*.title' => ['nullable', 'string', 'max:180'],
            'home_slides.*.subtitle' => ['nullable', 'string', 'max:500'],
            'home_slides.*.button_label' => ['nullable', 'string', 'max:60'],
            'home_slides.*.button_url' => ['nullable', 'string', 'max:500'],
            'about_title' => ['nullable', 'string', 'max:255'],
            'about_body' => ['nullable', 'string', 'max:10000'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_body' => ['nullable', 'string', 'max:5000'],
            'faq_title' => ['nullable', 'string', 'max:255'],
            'faq_1_question' => ['nullable', 'string', 'max:255'], 'faq_1_answer' => ['nullable', 'string', 'max:2000'],
            'faq_2_question' => ['nullable', 'string', 'max:255'], 'faq_2_answer' => ['nullable', 'string', 'max:2000'],
            'faq_3_question' => ['nullable', 'string', 'max:255'], 'faq_3_answer' => ['nullable', 'string', 'max:2000'],
            'faq_4_question' => ['nullable', 'string', 'max:255'], 'faq_4_answer' => ['nullable', 'string', 'max:2000'],
            'faq_5_question' => ['nullable', 'string', 'max:255'], 'faq_5_answer' => ['nullable', 'string', 'max:2000'],
            'faq_6_question' => ['nullable', 'string', 'max:255'], 'faq_6_answer' => ['nullable', 'string', 'max:2000'],
            'faq_7_question' => ['nullable', 'string', 'max:255'], 'faq_7_answer' => ['nullable', 'string', 'max:2000'],
            'faq_8_question' => ['nullable', 'string', 'max:255'], 'faq_8_answer' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'google_analytics_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/i'],
            'google_tag_manager_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]+$/i'],
            'facebook_pixel_id' => ['nullable', 'regex:/^\d{5,20}$/'],
            'google_search_console_verification' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'attendance_allow_excused' => ['nullable', 'boolean'],
            'attendance_max_location_age_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'attendance_stale_location_action' => ['nullable', 'in:reject,manual_review'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.bkash.enabled' => ['required_with:payment_methods', 'boolean'],
            'payment_methods.bkash.label' => ['required_with:payment_methods', 'string', 'max:50'],
            'payment_methods.bkash.account' => ['nullable', 'string', 'max:120'],
            'payment_methods.bkash.instructions' => ['nullable', 'string', 'max:500'],
            'payment_methods.rocket.enabled' => ['required_with:payment_methods', 'boolean'],
            'payment_methods.rocket.label' => ['required_with:payment_methods', 'string', 'max:50'],
            'payment_methods.rocket.account' => ['nullable', 'string', 'max:120'],
            'payment_methods.rocket.instructions' => ['nullable', 'string', 'max:500'],
            'payment_methods.bank.enabled' => ['required_with:payment_methods', 'boolean'],
            'payment_methods.bank.label' => ['required_with:payment_methods', 'string', 'max:50'],
            'payment_methods.bank.account' => ['nullable', 'string', 'max:120'],
            'payment_methods.bank.instructions' => ['nullable', 'string', 'max:500'],
            'payment_methods.manual.enabled' => ['required_with:payment_methods', 'boolean'],
            'payment_methods.manual.label' => ['required_with:payment_methods', 'string', 'max:50'],
            'payment_methods.manual.account' => ['nullable', 'string', 'max:120'],
            'payment_methods.manual.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $paymentMethods = $validated['payment_methods'] ?? Setting::paymentMethods();
        $slides = collect($validated['home_slides'] ?? [])->take(3)->map(function (array $slide) {
            $image = !empty($slide['image_id']) ? MediaAsset::query()->whereKey($slide['image_id'])->where('mime_type', 'like', 'image/%')->first() : null;
            if (empty($slide['enabled']) || !$image) {
                return null;
            }

            $buttonUrl = trim($slide['button_url'] ?? '');
            if ($buttonUrl !== '' && !preg_match('~^(https?://|/(?!/)|#)~i', $buttonUrl)) {
                $buttonUrl = '';
            }

            return [
                'image_id' => $image->id,
                'title' => trim($slide['title'] ?? ''),
                'subtitle' => trim($slide['subtitle'] ?? ''),
                'button_label' => trim($slide['button_label'] ?? ''),
                'button_url' => $buttonUrl,
                'enabled' => true,
            ];
        })->filter()->values()->all();
        unset($validated['payment_methods'], $validated['home_slides']);

        foreach (['site_logo_media_id', 'site_favicon_media_id', 'loading_screen_logo_media_id', 'dashboard_hero_image_media_id', 'dashboard_live_class_image_media_id'] as $mediaSetting) {
            $mediaId = $validated[$mediaSetting] ?? null;
            if ($mediaId !== null && !MediaAsset::query()->whereKey($mediaId)->where('mime_type', 'like', 'image/%')->exists()) {
                return back()->withErrors([$mediaSetting => 'Please select a valid image from the Media Library.'])->withInput();
            }
        }

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }

        Setting::setValue('payment_methods', json_encode($paymentMethods, JSON_THROW_ON_ERROR));
        Setting::setValue('home_slides', json_encode($slides, JSON_THROW_ON_ERROR));

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
    }
}
