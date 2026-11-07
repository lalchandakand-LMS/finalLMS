@extends('layouts.app')

@section('title', __('Homepage Control') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">{{ __('Homepage control') }}</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Back to dashboard') }}</a>
    </div>

    <div class="panel" style="max-width: 980px; margin: 0 auto;">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @php($savedSlides = json_decode((string) ($settings['home_slides'] ?? '[]'), true) ?: [])

            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label>{{ __('Hero title') }}</label>
                    <input type="text" name="home_title" value="{{ old('home_title', $settings['home_title'] ?? 'Grow your skills with a complete LMS experience.') }}">
                </div>
                <div>
                    <label>{{ __('Featured section title') }}</label>
                    <input type="text" name="home_featured_title" value="{{ old('home_featured_title', $settings['home_featured_title'] ?? 'Popular learning tracks') }}">
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <label>{{ __('Hero subtitle') }}</label>
                <textarea name="home_subtitle" rows="4">{{ old('home_subtitle', $settings['home_subtitle'] ?? 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.') }}</textarea>
            </div>

            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label>{{ __('Primary CTA label') }}</label>
                    <input type="text" name="home_cta_primary" value="{{ old('home_cta_primary', $settings['home_cta_primary'] ?? 'Browse courses') }}">
                </div>
                <div>
                    <label>{{ __('Secondary CTA label') }}</label>
                    <input type="text" name="home_cta_secondary" value="{{ old('home_cta_secondary', $settings['home_cta_secondary'] ?? 'Login') }}">
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <label>{{ __('Why learners stay section title') }}</label>
                <input type="text" name="home_reason_title" value="{{ old('home_reason_title', $settings['home_reason_title'] ?? 'Built to keep you moving forward') }}">
            </div>

            <div class="grid grid-3" style="margin-bottom: 18px;">
                <div>
                    <label>{{ __('Metric 1 value') }}</label>
                    <input type="text" name="home_metric_1_value" value="{{ old('home_metric_1_value', $settings['home_metric_1_value'] ?? '12+') }}">
                </div>
                <div>
                    <label>{{ __('Metric 1 label') }}</label>
                    <input type="text" name="home_metric_1_label" value="{{ old('home_metric_1_label', $settings['home_metric_1_label'] ?? 'Active courses') }}">
                </div>
                <div>
                    <label>{{ __('Metric 2 value') }}</label>
                    <input type="text" name="home_metric_2_value" value="{{ old('home_metric_2_value', $settings['home_metric_2_value'] ?? '4.9/5') }}">
                </div>
                <div>
                    <label>{{ __('Metric 2 label') }}</label>
                    <input type="text" name="home_metric_2_label" value="{{ old('home_metric_2_label', $settings['home_metric_2_label'] ?? 'Learner score') }}">
                </div>
                <div>
                    <label>{{ __('Metric 3 value') }}</label>
                    <input type="text" name="home_metric_3_value" value="{{ old('home_metric_3_value', $settings['home_metric_3_value'] ?? '24/7') }}">
                </div>
                <div>
                    <label>{{ __('Metric 3 label') }}</label>
                    <input type="text" name="home_metric_3_label" value="{{ old('home_metric_3_label', $settings['home_metric_3_label'] ?? 'Support') }}">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">{{ __('Homepage slider') }}</span>
                <h2 style="margin: 12px 0 6px;">{{ __('Hero image slides') }}</h2>
                <p class="muted" style="margin: 0;">{{ __('Choose up to three images from Media Library. Upload images there first, then select them here. Only enabled slides with an image are shown.') }} <a href="{{ route('admin.media.index') }}">{{ __('Open Media Library') }}</a></p>
            </div>
            @for($i = 0; $i < 3; $i++)
                @php($savedSlide = $savedSlides[$i] ?? [])
                <section class="panel" style="padding:18px;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
                        <h3 style="margin:0;">{{ __('Slide') }} {{ $i + 1 }}</h3>
                        <label style="display:flex;align-items:center;gap:8px;margin:0;">
                            <input type="hidden" name="home_slides[{{ $i }}][enabled]" value="0">
                            <input type="checkbox" name="home_slides[{{ $i }}][enabled]" value="1" style="width:auto;" @checked(old("home_slides.$i.enabled", $savedSlide['enabled'] ?? false))>
                            {{ __('Enabled') }}
                        </label>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label for="home-slide-{{ $i }}-image">{{ __('Media Library image') }}</label>
                        <select id="home-slide-{{ $i }}-image" name="home_slides[{{ $i }}][image_id]">
                            <option value="">{{ __('Select an image') }}</option>
                            @foreach($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old("home_slides.$i.image_id", $savedSlide['image_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }} — {{ $asset->alt_text ?: $asset->mime_type }}</option>
                            @endforeach
                        </select>
                        @if($mediaAssets->isEmpty())
                            <small class="muted">{{ __('No images available. Upload JPG, PNG, WebP or GIF files in Media Library first.') }}</small>
                        @endif
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <label for="home-slide-{{ $i }}-title">{{ __('Slide title') }}</label>
                            <input id="home-slide-{{ $i }}-title" name="home_slides[{{ $i }}][title]" maxlength="180" value="{{ old("home_slides.$i.title", $savedSlide['title'] ?? '') }}">
                        </div>
                        <div>
                            <label for="home-slide-{{ $i }}-subtitle">{{ __('Slide subtitle') }}</label>
                            <textarea id="home-slide-{{ $i }}-subtitle" name="home_slides[{{ $i }}][subtitle]" rows="2" maxlength="500">{{ old("home_slides.$i.subtitle", $savedSlide['subtitle'] ?? '') }}</textarea>
                        </div>
                        <div>
                            <label for="home-slide-{{ $i }}-button-label">{{ __('Button label') }}</label>
                            <input id="home-slide-{{ $i }}-button-label" name="home_slides[{{ $i }}][button_label]" maxlength="60" value="{{ old("home_slides.$i.button_label", $savedSlide['button_label'] ?? '') }}">
                        </div>
                        <div>
                            <label for="home-slide-{{ $i }}-button-url">{{ __('Button URL') }}</label>
                            <input id="home-slide-{{ $i }}-button-url" name="home_slides[{{ $i }}][button_url]" maxlength="500" value="{{ old("home_slides.$i.button_url", $savedSlide['button_url'] ?? '') }}" placeholder="/courses">
                        </div>
                    </div>
                </section>
            @endfor

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">{{ __('Public website pages') }}</span>
                <h2 style="margin: 12px 0 6px;">{{ __('About and contact') }}</h2>
                <p class="muted" style="margin: 0;">{{ __('Text is safely escaped on the public pages. Use plain text and line breaks.') }}</p>
            </div>
            <div class="grid grid-2" style="margin-bottom:18px;">
                <div>
                    <label for="about-title">{{ __('About page title') }}</label>
                    <input id="about-title" name="about_title" maxlength="255" value="{{ old('about_title', $settings['about_title'] ?? 'About us') }}">
                </div>
                <div>
                    <label for="contact-title">{{ __('Contact page title') }}</label>
                    <input id="contact-title" name="contact_title" maxlength="255" value="{{ old('contact_title', $settings['contact_title'] ?? 'Contact us') }}">
                </div>
            </div>
            <div class="grid grid-2" style="margin-bottom:18px;">
                <div>
                    <label for="about-body">{{ __('About page content') }}</label>
                    <textarea id="about-body" name="about_body" rows="7" maxlength="10000">{{ old('about_body', $settings['about_body'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="contact-body">{{ __('Contact details and instructions') }}</label>
                    <textarea id="contact-body" name="contact_body" rows="7" maxlength="5000">{{ old('contact_body', $settings['contact_body'] ?? '') }}</textarea>
                </div>
            </div>
            <div style="margin-bottom:18px;">
                <label for="faq-title">{{ __('FAQ page title') }}</label>
                <input id="faq-title" name="faq_title" maxlength="255" value="{{ old('faq_title', $settings['faq_title'] ?? 'Frequently asked questions') }}">
            </div>
            @for($i = 1; $i <= 8; $i++)
                <section class="grid grid-2" style="padding:14px 0;border-top:1px solid var(--border);">
                    <div>
                        <label for="faq-{{ $i }}-question">Question {{ $i }}</label>
                        <input id="faq-{{ $i }}-question" name="faq_{{ $i }}_question" maxlength="255" value="{{ old("faq_{$i}_question", $settings["faq_{$i}_question"] ?? '') }}">
                    </div>
                    <div>
                        <label for="faq-{{ $i }}-answer">Answer {{ $i }}</label>
                        <textarea id="faq-{{ $i }}-answer" name="faq_{{ $i }}_answer" rows="2" maxlength="2000" style="min-height:70px;">{{ old("faq_{$i}_answer", $settings["faq_{$i}_answer"] ?? '') }}</textarea>
                    </div>
                </section>
            @endfor

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">{{ __('Search and marketing') }}</span>
                <h2 style="margin: 12px 0 6px;">{{ __('SEO and tracking') }}</h2>
                <p class="muted" style="margin: 0;">{{ __('Tracking scripts load only after a visitor accepts marketing cookies. Use either direct Google Analytics or Tag Manager to avoid duplicate analytics.') }}</p>
            </div>
            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label for="seo-title">{{ __('SEO title') }}</label>
                    <input id="seo-title" type="text" name="seo_title" maxlength="255" value="{{ old('seo_title', $settings['seo_title'] ?? '') }}">
                </div>
                <div>
                    <label for="seo-description">{{ __('SEO description') }}</label>
                    <textarea id="seo-description" name="seo_description" rows="3" maxlength="320" style="min-height:80px;">{{ old('seo_description', $settings['seo_description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="seo-keywords">{{ __('SEO keywords') }}</label>
                    <input id="seo-keywords" type="text" name="seo_keywords" maxlength="500" value="{{ old('seo_keywords', $settings['seo_keywords'] ?? '') }}">
                </div>
                <div>
                    <label for="google-analytics-id">{{ __('Google Analytics 4 ID') }}</label>
                    <input id="google-analytics-id" type="text" name="google_analytics_id" placeholder="G-XXXXXXXXXX" value="{{ old('google_analytics_id', $settings['google_analytics_id'] ?? '') }}">
                </div>
                <div>
                    <label for="google-tag-manager-id">{{ __('Google Tag Manager ID') }}</label>
                    <input id="google-tag-manager-id" type="text" name="google_tag_manager_id" placeholder="GTM-XXXXXXX" value="{{ old('google_tag_manager_id', $settings['google_tag_manager_id'] ?? '') }}">
                </div>
                <div>
                    <label for="facebook-pixel-id">{{ __('Meta (Facebook) Pixel ID') }}</label>
                    <input id="facebook-pixel-id" type="text" name="facebook_pixel_id" inputmode="numeric" value="{{ old('facebook_pixel_id', $settings['facebook_pixel_id'] ?? '') }}">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">{{ __('Bangladesh payments') }}</span>
                <h2 style="margin: 12px 0 6px;">{{ __('Payment methods') }}</h2>
                <p class="muted" style="margin: 0;">{{ __('Enable a method only after adding your receiving account and payment instructions. Submissions require manual review.') }}</p>
            </div>

            @foreach($paymentMethods as $key => $method)
                <section style="border-top: 1px solid var(--border); padding: 18px 0;">
                    <div class="grid grid-2">
                        <div>
                            <input type="hidden" name="payment_methods[{{ $key }}][enabled]" value="0">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="payment_methods[{{ $key }}][enabled]" value="1" style="width:auto;" @checked($method['enabled'])>
                                {{ __('Enable') }} {{ $method['label'] }}
                            </label>
                            <label for="payment-method-{{ $key }}-label">{{ __('Checkout label') }}</label>
                            <input id="payment-method-{{ $key }}-label" type="text" name="payment_methods[{{ $key }}][label]" value="{{ old("payment_methods.$key.label", $method['label']) }}" required>
                        </div>
                        <div>
                            <label for="payment-method-{{ $key }}-account">{{ __('Receiving account / bank details') }}</label>
                            <input id="payment-method-{{ $key }}-account" type="text" name="payment_methods[{{ $key }}][account]" value="{{ old("payment_methods.$key.account", $method['account']) }}" placeholder="Account or number shown to students">
                            <label for="payment-method-{{ $key }}-instructions" style="margin-top:12px;">{{ __('Payment instructions') }}</label>
                            <textarea id="payment-method-{{ $key }}-instructions" name="payment_methods[{{ $key }}][instructions]" rows="3" style="min-height:80px;">{{ old("payment_methods.$key.instructions", $method['instructions']) }}</textarea>
                        </div>
                    </div>
                </section>
            @endforeach

            <div style="display:flex; gap:12px; margin-top: 20px; flex-wrap: wrap;">
                <button type="submit" class="btn primary">{{ __('Save settings') }}</button>
                <a href="{{ route('landing') }}" class="btn secondary">{{ __('Preview home') }}</a>
            </div>
        </form>
    </div>
@endsection
