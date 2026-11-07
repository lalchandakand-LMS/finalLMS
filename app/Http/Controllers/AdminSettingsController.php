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

            return [
                'image_id' => $image->id,
                'title' => trim($slide['title'] ?? ''),
                'subtitle' => trim($slide['subtitle'] ?? ''),
                'button_label' => trim($slide['button_label'] ?? ''),
                'button_url' => trim($slide['button_url'] ?? ''),
                'enabled' => true,
            ];
        })->filter()->values()->all();
        unset($validated['payment_methods'], $validated['home_slides']);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }

        Setting::setValue('payment_methods', json_encode($paymentMethods, JSON_THROW_ON_ERROR));
        Setting::setValue('home_slides', json_encode($slides, JSON_THROW_ON_ERROR));

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
    }
}
