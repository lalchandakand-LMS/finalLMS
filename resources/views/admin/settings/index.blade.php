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

            <hr style="border:0;border-top:1px solid var(--border);margin:28px 0;">
            <section aria-labelledby="theme-customizer-title">
                <span class="badge info">{{ __('Admin appearance') }}</span>
                <h2 id="theme-customizer-title" style="margin:12px 0 6px;">{{ __('Theme & color customizer') }}</h2>
                <p class="muted">{{ __('Change dashboard, sidebar, buttons, cards, text and website colors without coding. The default header is a blue gradient.') }}</p>
                <div class="grid grid-2" id="aec-theme-fields" style="margin:18px 0;">
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_primary" style="margin:0;">{{ __('Primary / button color') }}</label>
                        <input id="theme_primary" type="color" name="theme_primary" value="{{ old('theme_primary', $settings['theme_primary'] ?? '#1769e8') }}" data-theme-var="theme_primary" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_primary_dark" style="margin:0;">{{ __('Dark primary / headings') }}</label>
                        <input id="theme_primary_dark" type="color" name="theme_primary_dark" value="{{ old('theme_primary_dark', $settings['theme_primary_dark'] ?? '#0b2253') }}" data-theme-var="theme_primary_dark" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_accent" style="margin:0;">{{ __('Accent color') }}</label>
                        <input id="theme_accent" type="color" name="theme_accent" value="{{ old('theme_accent', $settings['theme_accent'] ?? '#268cf5') }}" data-theme-var="theme_accent" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_background" style="margin:0;">{{ __('Page background') }}</label>
                        <input id="theme_background" type="color" name="theme_background" value="{{ old('theme_background', $settings['theme_background'] ?? '#f3f6fb') }}" data-theme-var="theme_background" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_surface" style="margin:0;">{{ __('Cards and panels') }}</label>
                        <input id="theme_surface" type="color" name="theme_surface" value="{{ old('theme_surface', $settings['theme_surface'] ?? '#ffffff') }}" data-theme-var="theme_surface" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_surface_alt" style="margin:0;">{{ __('Soft panels / highlights') }}</label>
                        <input id="theme_surface_alt" type="color" name="theme_surface_alt" value="{{ old('theme_surface_alt', $settings['theme_surface_alt'] ?? '#eef4ff') }}" data-theme-var="theme_surface_alt" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_text" style="margin:0;">{{ __('Main text color') }}</label>
                        <input id="theme_text" type="color" name="theme_text" value="{{ old('theme_text', $settings['theme_text'] ?? '#18212f') }}" data-theme-var="theme_text" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_muted" style="margin:0;">{{ __('Secondary text color') }}</label>
                        <input id="theme_muted" type="color" name="theme_muted" value="{{ old('theme_muted', $settings['theme_muted'] ?? '#5b6576') }}" data-theme-var="theme_muted" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_border" style="margin:0;">{{ __('Borders') }}</label>
                        <input id="theme_border" type="color" name="theme_border" value="{{ old('theme_border', $settings['theme_border'] ?? '#dfe7f6') }}" data-theme-var="theme_border" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_sidebar_start" style="margin:0;">{{ __('Sidebar top color') }}</label>
                        <input id="theme_sidebar_start" type="color" name="theme_sidebar_start" value="{{ old('theme_sidebar_start', $settings['theme_sidebar_start'] ?? '#102b63') }}" data-theme-var="theme_sidebar_start" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_sidebar_end" style="margin:0;">{{ __('Sidebar bottom color') }}</label>
                        <input id="theme_sidebar_end" type="color" name="theme_sidebar_end" value="{{ old('theme_sidebar_end', $settings['theme_sidebar_end'] ?? '#0b1d42') }}" data-theme-var="theme_sidebar_end" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_hero_start" style="margin:0;">{{ __('Dashboard header start (blue)') }}</label>
                        <input id="theme_hero_start" type="color" name="theme_hero_start" value="{{ old('theme_hero_start', $settings['theme_hero_start'] ?? '#0b2253') }}" data-theme-var="theme_hero_start" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_hero_middle" style="margin:0;">{{ __('Dashboard header middle (blue)') }}</label>
                        <input id="theme_hero_middle" type="color" name="theme_hero_middle" value="{{ old('theme_hero_middle', $settings['theme_hero_middle'] ?? '#0759c8') }}" data-theme-var="theme_hero_middle" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <label for="theme_hero_end" style="margin:0;">{{ __('Dashboard header end (blue)') }}</label>
                        <input id="theme_hero_end" type="color" name="theme_hero_end" value="{{ old('theme_hero_end', $settings['theme_hero_end'] ?? '#268cf5') }}" data-theme-var="theme_hero_end" style="width:58px;height:42px;padding:3px;cursor:pointer;">
                    </div>
                </div>
                <div id="aec-theme-preview" class="panel" style="background:linear-gradient(115deg,#0b2253,#0759c8 62%,#268cf5);color:#fff;margin-bottom:18px;">
                    <strong>{{ __('Live preview — Blue gradient dashboard header') }}</strong>
                    <p style="color:#e2edff;margin:6px 0 0;">{{ __('Selected colors preview here before saving.') }}</p>
                </div>
                <button type="button" class="btn secondary" id="aec-theme-reset">{{ __('Restore default blue theme') }}</button>
            </section>
            <script>
                (function () {
                    const defaults = {"theme_primary":"#1769e8","theme_primary_dark":"#0b2253","theme_accent":"#268cf5","theme_background":"#f3f6fb","theme_surface":"#ffffff","theme_surface_alt":"#eef4ff","theme_text":"#18212f","theme_muted":"#5b6576","theme_border":"#dfe7f6","theme_sidebar_start":"#102b63","theme_sidebar_end":"#0b1d42","theme_hero_start":"#0b2253","theme_hero_middle":"#0759c8","theme_hero_end":"#268cf5"};
                    const inputs = document.querySelectorAll('[data-theme-var]');
                    const preview = document.getElementById('aec-theme-preview');
                    const root = document.documentElement;
                    const vars = {theme_primary:'--primary',theme_primary_dark:'--primary-dark',theme_accent:'--accent',theme_background:'--bg',theme_surface:'--panel',theme_surface_alt:'--panel-alt',theme_text:'--text',theme_muted:'--muted',theme_border:'--border',theme_sidebar_start:'--admin-sidebar-start',theme_sidebar_end:'--admin-sidebar-end',theme_hero_start:'--dashboard-hero-start',theme_hero_middle:'--dashboard-hero-middle',theme_hero_end:'--dashboard-hero-end'};
                    function previewTheme() {
                        inputs.forEach(input => root.style.setProperty(vars[input.dataset.themeVar], input.value));
                        preview.style.background = 'linear-gradient(115deg,' + document.getElementById('theme_hero_start').value + ',' + document.getElementById('theme_hero_middle').value + ' 62%,' + document.getElementById('theme_hero_end').value + ')';
                    }
                    inputs.forEach(input => input.addEventListener('input', previewTheme));
                    document.getElementById('aec-theme-reset').addEventListener('click', () => {
                        inputs.forEach(input => { input.value = defaults[input.dataset.themeVar]; });
                        previewTheme();
                    });
                    previewTheme();
                })();
            </script>

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
