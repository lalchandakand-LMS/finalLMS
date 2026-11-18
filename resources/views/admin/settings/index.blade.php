@extends('layouts.app')

@section('title', __('Website & Institution Settings') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">{{ __('Website & institution settings') }}</h1>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;"><a href="{{ route('admin.setup-wizard.index') }}" class="btn secondary">{{ __('Initial setup wizard') }}</a><a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Back to dashboard') }}</a></div>
    </div>

    <div class="panel" style="max-width: 980px; margin: 0 auto;">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <section class="panel" style="padding:20px;margin-bottom:24px;border:1px solid var(--border);">
                <span class="badge info">{{ __('Institution identity') }}</span>
                <h2 style="margin:12px 0 6px;">{{ __('Make this installation your own') }}</h2>
                <p class="muted">{{ __('Set the identity shown to your students, staff and visitors. These details belong to this installation only.') }}</p>
                <div class="grid grid-2">
                    <div><label for="institution-name">{{ __('Institution name') }}</label><input id="institution-name" name="institution_name" required maxlength="160" value="{{ old('institution_name', $settings['institution_name'] ?? config('app.name')) }}"></div>
                    <div><label for="institution-short-name">{{ __('Short name') }}</label><input id="institution-short-name" name="institution_short_name" maxlength="60" value="{{ old('institution_short_name', $settings['institution_short_name'] ?? '') }}"></div>
                    <div><label for="institution-tagline">{{ __('Tagline') }}</label><input id="institution-tagline" name="institution_tagline" maxlength="180" value="{{ old('institution_tagline', $settings['institution_tagline'] ?? '') }}"></div>
                    <div><label for="institution-contact-email">{{ __('Contact email') }}</label><input id="institution-contact-email" type="email" name="institution_contact_email" maxlength="190" value="{{ old('institution_contact_email', $settings['institution_contact_email'] ?? '') }}"></div>
                    <div><label for="institution-contact-phone">{{ __('Contact phone') }}</label><input id="institution-contact-phone" name="institution_contact_phone" maxlength="40" value="{{ old('institution_contact_phone', $settings['institution_contact_phone'] ?? '') }}"></div>
                    <div><label for="institution-website">{{ __('Website URL') }}</label><input id="institution-website" type="url" name="institution_website" maxlength="255" placeholder="https://example.edu" value="{{ old('institution_website', $settings['institution_website'] ?? '') }}"></div>
                    <div><label for="institution-logo-url">{{ __('Logo URL') }}</label><input id="institution-logo-url" type="url" name="institution_logo_url" maxlength="500" placeholder="https://example.edu/logo.png" value="{{ old('institution_logo_url', $settings['institution_logo_url'] ?? '') }}"></div>
                    <div><label for="institution-favicon-url">{{ __('Favicon URL') }}</label><input id="institution-favicon-url" type="url" name="institution_favicon_url" maxlength="500" placeholder="https://example.edu/favicon.ico" value="{{ old('institution_favicon_url', $settings['institution_favicon_url'] ?? '') }}"></div>
                    <div>
                        <label for="institution-logo-media">{{ __('Logo from Media Library') }}</label>
                        <select id="institution-logo-media" name="site_logo_media_id">
                            <option value="">{{ __('Use logo URL / no uploaded logo') }}</option>
                            @foreach($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('site_logo_media_id', $settings['site_logo_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="institution-favicon-media">{{ __('Favicon from Media Library') }}</label>
                        <select id="institution-favicon-media" name="site_favicon_media_id">
                            <option value="">{{ __('Use favicon URL / no uploaded favicon') }}</option>
                            @foreach($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('site_favicon_media_id', $settings['site_favicon_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="institution-default-locale">{{ __('Default language') }}</label><select id="institution-default-locale" name="institution_default_locale"><option value="en" @selected(old('institution_default_locale', $settings['institution_default_locale'] ?? 'en') === 'en')>{{ __('English') }}</option><option value="bn" @selected(old('institution_default_locale', $settings['institution_default_locale'] ?? 'en') === 'bn')>{{ __('বাংলা') }}</option></select></div>
                    <div><label for="institution-timezone">{{ __('Institution timezone') }}</label><select id="institution-timezone" name="institution_timezone">@foreach(['Asia/Dhaka' => 'Bangladesh (Dhaka)', 'Asia/Kolkata' => 'India (Kolkata)', 'Asia/Karachi' => 'Pakistan (Karachi)', 'Asia/Dubai' => 'UAE (Dubai)', 'Europe/London' => 'United Kingdom (London)', 'UTC' => 'UTC'] as $timezone => $label)<option value="{{ $timezone }}" @selected(old('institution_timezone', $settings['institution_timezone'] ?? 'Asia/Dhaka') === $timezone)>{{ $label }}</option>@endforeach</select></div>
                    <div style="grid-column:1/-1;" class="muted">{{ __('Financial records currently use BDT as their stored currency. Per-record currency support must be added before changing this installation’s currency, so existing invoices and payments are never relabelled incorrectly.') }}</div>
                    <div style="grid-column:1/-1;"><label for="institution-address">{{ __('Institution address') }}</label><textarea id="institution-address" name="institution_address" rows="2" maxlength="500">{{ old('institution_address', $settings['institution_address'] ?? '') }}</textarea></div>
                    <div><label for="site-primary-color">{{ __('Primary brand color') }}</label><input id="site-primary-color" type="color" name="site_primary_color" value="{{ old('site_primary_color', $settings['site_primary_color'] ?? '#1546a8') }}"></div>
                    <div><label for="site-background-color">{{ __('Website background color') }}</label><input id="site-background-color" type="color" name="site_background_color" value="{{ old('site_background_color', $settings['site_background_color'] ?? '#f3f6fb') }}"></div>
                </div>
                <small class="muted">{{ __('Use HTTPS image URLs. Direct Media Library upload and selection will be integrated into the complete identity editor.') }}</small>
            </section>
            <section class="panel" style="padding:20px;margin-bottom:24px;border:1px solid var(--border);">
                <span class="badge info">{{ __('Brand experience') }}</span>
                <h2 style="margin:12px 0 6px;">{{ __('Custom loading screen') }}</h2>
                <p class="muted">{{ __('Replace the default spinning indicator with your own institution logo. The loader appears during page navigation and form submissions.') }}</p>
                <div class="grid grid-2">
                    <div><label for="loading-screen-enabled">{{ __('Loading screen') }}</label><select id="loading-screen-enabled" name="loading_screen_enabled"><option value="1" @selected(old('loading_screen_enabled', $settings['loading_screen_enabled'] ?? '1') === '1')>{{ __('Enabled') }}</option><option value="0" @selected(old('loading_screen_enabled', $settings['loading_screen_enabled'] ?? '1') === '0')>{{ __('Disabled') }}</option></select></div>
                    <div><label for="loading-screen-logo">{{ __('Logo from Media Library') }}</label><select id="loading-screen-logo" name="loading_screen_logo_media_id"><option value="">{{ __('Use website logo') }}</option>@foreach($mediaAssets as $asset)<option value="{{ $asset->id }}" @selected((string) old('loading_screen_logo_media_id', $settings['loading_screen_logo_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>@endforeach</select><small class="muted">{{ __('Choose an image in Media Library, or leave blank to use the institution logo.') }} <a href="{{ route('admin.media.index') }}">{{ __('Open Media Library') }}</a></small></div>
                    <div><label for="loading-screen-style">{{ __('Animation style') }}</label><select id="loading-screen-style" name="loading_screen_style"><option value="orbit" @selected(old('loading_screen_style', $settings['loading_screen_style'] ?? 'orbit') === 'orbit')>{{ __('Orbit') }}</option><option value="pulse" @selected(old('loading_screen_style', $settings['loading_screen_style'] ?? 'orbit') === 'pulse')>{{ __('Logo pulse') }}</option><option value="ring" @selected(old('loading_screen_style', $settings['loading_screen_style'] ?? 'orbit') === 'ring')>{{ __('Rotating ring') }}</option></select></div>
                    <div><label for="loading-screen-text">{{ __('Loading text') }}</label><input id="loading-screen-text" name="loading_screen_text" maxlength="60" value="{{ old('loading_screen_text', $settings['loading_screen_text'] ?? 'Loading...') }}"></div>
                    <div><label for="loading-screen-background">{{ __('Background color') }}</label><input id="loading-screen-background" type="color" name="loading_screen_background" value="{{ old('loading_screen_background', $settings['loading_screen_background'] ?? '#ffffff') }}"></div>
                    <div><label for="loading-screen-speed">{{ __('Animation speed') }}</label><input id="loading-screen-speed" type="range" name="loading_screen_speed" min="6" max="24" value="{{ old('loading_screen_speed', $settings['loading_screen_speed'] ?? '12') }}"><small class="muted">{{ __('Higher value means faster animation.') }}</small></div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-top:16px;padding:16px;border:1px dashed var(--border);border-radius:12px;background:{{ old('loading_screen_background', $settings['loading_screen_background'] ?? '#ffffff') }};">
                    @if(!empty($settings['loading_screen_logo_media_id']) && ($previewLogo = $mediaAssets->firstWhere('id', (int) $settings['loading_screen_logo_media_id'])))<img src="{{ $previewLogo->url }}" alt="" style="width:54px;height:54px;object-fit:contain;">@elseif($institutionLogoUrl = ($settings['institution_logo_url'] ?? null))<img src="{{ $institutionLogoUrl }}" alt="" style="width:54px;height:54px;object-fit:contain;">@else<span style="font-size:2rem;">📘</span>@endif
                    <strong>{{ old('loading_screen_text', $settings['loading_screen_text'] ?? 'Loading...') }}</strong><span class="muted">{{ __('Preview') }}</span>
                </div>
            </section>
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

            <div class="panel" style="padding:18px;margin-bottom:18px;">
                <h3>{{ __('Slider behaviour and dimensions') }}</h3>
                <div class="grid grid-3">
                    <div><label>{{ __('Autoplay') }}</label><select name="home_slider_autoplay"><option value="1" @selected(old('home_slider_autoplay',$settings['home_slider_autoplay'] ?? '1') == '1')>{{ __('On') }}</option><option value="0" @selected(old('home_slider_autoplay',$settings['home_slider_autoplay'] ?? '1') == '0')>{{ __('Off') }}</option></select></div>
                    <div><label>{{ __('Slide interval (milliseconds)') }}</label><input type="number" name="home_slider_interval" min="3000" max="12000" step="500" value="{{ old('home_slider_interval',$settings['home_slider_interval'] ?? 5000) }}"></div>
                    <div><label>{{ __('Desktop height (px)') }}</label><input type="number" name="home_slider_height" min="320" max="720" value="{{ old('home_slider_height',$settings['home_slider_height'] ?? 480) }}"></div>
                    <div><label>{{ __('Mobile height (px)') }}</label><input type="number" name="home_slider_mobile_height" min="220" max="520" value="{{ old('home_slider_mobile_height',$settings['home_slider_mobile_height'] ?? 300) }}"></div>
                    <div><label>{{ __('Image overlay (%)') }}</label><input type="number" name="home_slider_overlay" min="20" max="90" value="{{ old('home_slider_overlay',$settings['home_slider_overlay'] ?? 60) }}"></div>
                    <div><label>{{ __('Pause on hover') }}</label><select name="home_slider_pause_hover"><option value="1" @selected(old('home_slider_pause_hover',$settings['home_slider_pause_hover'] ?? '1') == '1')>{{ __('On') }}</option><option value="0" @selected(old('home_slider_pause_hover',$settings['home_slider_pause_hover'] ?? '1') == '0')>{{ __('Off') }}</option></select></div>
                    <div><label>{{ __('Navigation arrows') }}</label><select name="home_slider_arrows"><option value="1" @selected(old('home_slider_arrows',$settings['home_slider_arrows'] ?? '1') == '1')>{{ __('Show') }}</option><option value="0" @selected(old('home_slider_arrows',$settings['home_slider_arrows'] ?? '1') == '0')>{{ __('Hide') }}</option></select></div>
                    <div><label>{{ __('Navigation dots') }}</label><select name="home_slider_dots"><option value="1" @selected(old('home_slider_dots',$settings['home_slider_dots'] ?? '1') == '1')>{{ __('Show') }}</option><option value="0" @selected(old('home_slider_dots',$settings['home_slider_dots'] ?? '1') == '0')>{{ __('Hide') }}</option></select></div>
                </div>
            </div>
            <hr style="border:0;border-top:1px solid var(--border);margin:28px 0;">
            <div style="margin-bottom:18px;"><span class="badge info">{{ __('Homepage section headings') }}</span><h2 style="margin:12px 0 6px;">{{ __('Edit section titles') }}</h2></div>
            <div class="grid grid-2" style="margin-bottom:18px;">
                <div><label>{{ __('Batch panel title') }}</label><input name="home_batches_title" maxlength="120" value="{{ old('home_batches_title',$settings['home_batches_title'] ?? 'Online Batches are ongoing!') }}"></div>
                <div><label>{{ __('Statistics panel title') }}</label><input name="home_stats_title" maxlength="120" value="{{ old('home_stats_title',$settings['home_stats_title'] ?? 'See the stats!') }}"></div>
                <div><label>{{ __('Learning path section title') }}</label><input name="home_paths_title" maxlength="120" value="{{ old('home_paths_title',$settings['home_paths_title'] ?? 'Choose Your Learning Path') }}"></div>
                <div><label>{{ __('Student reviews section title') }}</label><input name="home_reviews_title" maxlength="120" value="{{ old('home_reviews_title',$settings['home_reviews_title'] ?? 'What Our Students Say') }}"></div>
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
                <div>
                    <label for="google-search-console-verification">{{ __('Google Search Console verification token') }}</label>
                    <input id="google-search-console-verification" type="text" name="google_search_console_verification" maxlength="255" autocomplete="off" value="{{ old('google_search_console_verification', $settings['google_search_console_verification'] ?? '') }}">
                    <small class="muted">{{ __('Paste only the content value from the Google site verification meta tag, not the full HTML tag.') }}</small>
                </div>
                <div>
                    <label>{{ __('Sitemap URL') }}</label>
                    <input type="text" readonly value="{{ url('/sitemap.xml') }}">
                    <small class="muted">{{ __('Submit this URL in Google Search Console after your website is publicly accessible.') }}</small>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">{{ __('Bangladesh payments') }}</span>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <section style="margin-bottom:24px;">
                <span class="badge info">{{ __('Attendance policy') }}</span>
                <h2 style="margin:12px 0 6px;">{{ __('Attendance status rules') }}</h2>
                <p class="muted">{{ __('Choose whether instructors may mark an enrolled student as excused. The default is disabled; changes apply to web and mobile attendance validation.') }}</p>
                <input type="hidden" name="attendance_allow_excused" value="0">
                <div style="margin-top:16px;max-width:420px;">
                    <label for="attendance-stale-action">{{ __('When a location capture is stale') }}</label>
                    <select id="attendance-stale-action" name="attendance_stale_location_action">
                        <option value="reject" @selected(old('attendance_stale_location_action', $settings['attendance_stale_location_action'] ?? 'reject') === 'reject')>{{ __('Reject sync') }}</option>
                        <option value="manual_review" @selected(old('attendance_stale_location_action', $settings['attendance_stale_location_action'] ?? 'reject') === 'manual_review')>{{ __('Send to administrator review queue') }}</option>
                    </select>
                    <p><a href="{{ route('admin.attendance-sync-reviews.index') }}">{{ __('Open attendance manual-review queue') }}</a></p>
                </div>
                <div style="margin-top:16px;max-width:420px;">
                    <label for="attendance-max-location-age">{{ __('Maximum offline location age (minutes)') }}</label>
                    <input id="attendance-max-location-age" type="number" name="attendance_max_location_age_minutes" min="1" max="1440" required value="{{ old('attendance_max_location_age_minutes', $settings['attendance_max_location_age_minutes'] ?? '10') }}">
                    <small class="muted">{{ __('Older captures are rejected by sync and appear as failed records; staff can verify them through the existing attendance correction process. Choose 1 to 1440 minutes.') }}</small>
                </div>
                <label style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="attendance_allow_excused" value="1" style="width:auto;" @checked(old('attendance_allow_excused', $settings['attendance_allow_excused'] ?? '0') === '1')>
                    {{ __('Allow Excused attendance status') }}
                </label>
            </section>
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
