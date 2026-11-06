@extends('layouts.app')

@section('title', 'Homepage Control | LMS Academy')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">Homepage control</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Back to dashboard</a>
    </div>

    <div class="panel" style="max-width: 980px; margin: 0 auto;">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label>Hero title</label>
                    <input type="text" name="home_title" value="{{ old('home_title', $settings['home_title'] ?? 'Grow your skills with a complete LMS experience.') }}">
                </div>
                <div>
                    <label>Featured section title</label>
                    <input type="text" name="home_featured_title" value="{{ old('home_featured_title', $settings['home_featured_title'] ?? 'Popular learning tracks') }}">
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <label>Hero subtitle</label>
                <textarea name="home_subtitle" rows="4">{{ old('home_subtitle', $settings['home_subtitle'] ?? 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.') }}</textarea>
            </div>

            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label>Primary CTA label</label>
                    <input type="text" name="home_cta_primary" value="{{ old('home_cta_primary', $settings['home_cta_primary'] ?? 'Browse courses') }}">
                </div>
                <div>
                    <label>Secondary CTA label</label>
                    <input type="text" name="home_cta_secondary" value="{{ old('home_cta_secondary', $settings['home_cta_secondary'] ?? 'Login') }}">
                </div>
            </div>

            <div style="margin-bottom: 18px;">
                <label>Why learners stay section title</label>
                <input type="text" name="home_reason_title" value="{{ old('home_reason_title', $settings['home_reason_title'] ?? 'Built to keep you moving forward') }}">
            </div>

            <div class="grid grid-3" style="margin-bottom: 18px;">
                <div>
                    <label>Metric 1 value</label>
                    <input type="text" name="home_metric_1_value" value="{{ old('home_metric_1_value', $settings['home_metric_1_value'] ?? '12+') }}">
                </div>
                <div>
                    <label>Metric 1 label</label>
                    <input type="text" name="home_metric_1_label" value="{{ old('home_metric_1_label', $settings['home_metric_1_label'] ?? 'Active courses') }}">
                </div>
                <div>
                    <label>Metric 2 value</label>
                    <input type="text" name="home_metric_2_value" value="{{ old('home_metric_2_value', $settings['home_metric_2_value'] ?? '4.9/5') }}">
                </div>
                <div>
                    <label>Metric 2 label</label>
                    <input type="text" name="home_metric_2_label" value="{{ old('home_metric_2_label', $settings['home_metric_2_label'] ?? 'Learner score') }}">
                </div>
                <div>
                    <label>Metric 3 value</label>
                    <input type="text" name="home_metric_3_value" value="{{ old('home_metric_3_value', $settings['home_metric_3_value'] ?? '24/7') }}">
                </div>
                <div>
                    <label>Metric 3 label</label>
                    <input type="text" name="home_metric_3_label" value="{{ old('home_metric_3_label', $settings['home_metric_3_label'] ?? 'Support') }}">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">Search and marketing</span>
                <h2 style="margin: 12px 0 6px;">SEO and tracking</h2>
                <p class="muted" style="margin: 0;">Tracking scripts load only after a visitor accepts marketing cookies. Use either direct Google Analytics or Tag Manager to avoid duplicate analytics.</p>
            </div>
            <div class="grid grid-2" style="margin-bottom: 18px;">
                <div>
                    <label for="seo-title">SEO title</label>
                    <input id="seo-title" type="text" name="seo_title" maxlength="255" value="{{ old('seo_title', $settings['seo_title'] ?? '') }}">
                </div>
                <div>
                    <label for="seo-description">SEO description</label>
                    <textarea id="seo-description" name="seo_description" rows="3" maxlength="320" style="min-height:80px;">{{ old('seo_description', $settings['seo_description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="seo-keywords">SEO keywords</label>
                    <input id="seo-keywords" type="text" name="seo_keywords" maxlength="500" value="{{ old('seo_keywords', $settings['seo_keywords'] ?? '') }}">
                </div>
                <div>
                    <label for="google-analytics-id">Google Analytics 4 ID</label>
                    <input id="google-analytics-id" type="text" name="google_analytics_id" placeholder="G-XXXXXXXXXX" value="{{ old('google_analytics_id', $settings['google_analytics_id'] ?? '') }}">
                </div>
                <div>
                    <label for="google-tag-manager-id">Google Tag Manager ID</label>
                    <input id="google-tag-manager-id" type="text" name="google_tag_manager_id" placeholder="GTM-XXXXXXX" value="{{ old('google_tag_manager_id', $settings['google_tag_manager_id'] ?? '') }}">
                </div>
                <div>
                    <label for="facebook-pixel-id">Meta (Facebook) Pixel ID</label>
                    <input id="facebook-pixel-id" type="text" name="facebook_pixel_id" inputmode="numeric" value="{{ old('facebook_pixel_id', $settings['facebook_pixel_id'] ?? '') }}">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 28px 0;">
            <div style="margin-bottom: 18px;">
                <span class="badge info">Bangladesh payments</span>
                <h2 style="margin: 12px 0 6px;">Payment methods</h2>
                <p class="muted" style="margin: 0;">Enable a method only after adding your receiving account and payment instructions. Submissions require manual review.</p>
            </div>

            @foreach($paymentMethods as $key => $method)
                <section style="border-top: 1px solid var(--border); padding: 18px 0;">
                    <div class="grid grid-2">
                        <div>
                            <input type="hidden" name="payment_methods[{{ $key }}][enabled]" value="0">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="payment_methods[{{ $key }}][enabled]" value="1" style="width:auto;" @checked($method['enabled'])>
                                Enable {{ $method['label'] }}
                            </label>
                            <label for="payment-method-{{ $key }}-label">Checkout label</label>
                            <input id="payment-method-{{ $key }}-label" type="text" name="payment_methods[{{ $key }}][label]" value="{{ old("payment_methods.$key.label", $method['label']) }}" required>
                        </div>
                        <div>
                            <label for="payment-method-{{ $key }}-account">Receiving account / bank details</label>
                            <input id="payment-method-{{ $key }}-account" type="text" name="payment_methods[{{ $key }}][account]" value="{{ old("payment_methods.$key.account", $method['account']) }}" placeholder="Account or number shown to students">
                            <label for="payment-method-{{ $key }}-instructions" style="margin-top:12px;">Payment instructions</label>
                            <textarea id="payment-method-{{ $key }}-instructions" name="payment_methods[{{ $key }}][instructions]" rows="3" style="min-height:80px;">{{ old("payment_methods.$key.instructions", $method['instructions']) }}</textarea>
                        </div>
                    </div>
                </section>
            @endforeach

            <div style="display:flex; gap:12px; margin-top: 20px; flex-wrap: wrap;">
                <button type="submit" class="btn primary">Save settings</button>
                <a href="{{ route('landing') }}" class="btn secondary">Preview home</a>
            </div>
        </form>
    </div>
@endsection
