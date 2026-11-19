@php
    $p = $payload ?? [];
    $isPreview = (bool) ($previewMode ?? false);
    $layout = $p['layout'] ?? 'modal';
    $placement = $p['placement'] ?? 'center';
    $maxWidth = max(280, min(1200, (int) ($p['max_width_px'] ?? 620)));
    $bgColor = $p['background_color'] ?? '#ffffff';
    $textColor = $p['text_color'] ?? '#14213d';
    $accentColor = $p['accent_color'] ?? '#1546a8';
    $fontScale = match ($p['font_scale'] ?? 'normal') {
        'compact' => '0.92rem',
        'large' => '1.12rem',
        default => '1rem',
    };
    $overlayAlpha = number_format(max(0, min(95, (int) ($p['overlay_opacity'] ?? 70))) / 100, 2, '.', '');
    $animation = $p['animation_style'] ?? 'fade';
    $respectReducedMotion = (bool) ($p['respect_reduced_motion'] ?? true);
    $hideOnMobile = (bool) ($p['hide_on_mobile'] ?? false);
    $hideOnDesktop = (bool) ($p['hide_on_desktop'] ?? false);
    $dismissible = (bool) ($p['dismissible'] ?? true);
    $showClose = (bool) ($p['show_close_button'] ?? true);
    $badgeClass = ($p['popup_type'] ?? 'offer') === 'ad'
        ? 'warning'
        : (($p['popup_type'] ?? 'offer') === 'registration' ? 'success' : 'info');
    $badgeLabel = match ($p['popup_type'] ?? 'offer') {
        'ad' => __('Advertisement'),
        'registration' => __('Join us'),
        'announcement' => __('Announcement'),
        'post' => __('Post promotion'),
        'banner' => __('Banner'),
        default => __('Offer'),
    };
    $isModal = $layout === 'modal';
    $isFloating = $layout === 'corner_card';
    $isBanner = in_array($layout, ['full_banner', 'announcement_strip'], true);
    $isTop = $placement === 'top';
    $isLeft = $placement === 'bottom_left';
@endphp

<style>
    .aec-popup-root[hidden] { display: none !important; }
    .aec-popup-root {
        position: fixed;
        z-index: 1000;
        font-size: {{ $fontScale }};
        @if($isModal)
            inset: 0;
            background: rgba(8, 18, 40, {{ $overlayAlpha }});
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        @elseif($isFloating)
            {{ $isTop ? 'top: 20px;' : 'bottom: 20px;' }}
            {{ $isLeft ? 'left: 20px;' : 'right: 20px;' }}
            max-width: min({{ min($maxWidth, 440) }}px, calc(100vw - 28px));
        @else
            left: 0;
            right: 0;
            {{ $isTop ? 'top: 0;' : 'bottom: 0;' }}
            padding: {{ $layout === 'announcement_strip' ? '0' : '12px 18px' }};
            display: flex;
            justify-content: center;
        @endif
    }
    .aec-popup-card {
        position: relative;
        width: {{ $isModal ? "min({$maxWidth}px, 100%)" : ($isBanner ? ($layout === 'announcement_strip' ? '100%' : "min({$maxWidth}px, 100%)") : '100%') }};
        max-height: {{ $layout === 'announcement_strip' ? 'auto' : '90vh' }};
        overflow: auto;
        background: {{ $bgColor }};
        color: {{ $textColor }};
        border-radius: {{ $layout === 'announcement_strip' ? '0' : '18px' }};
        box-shadow: 0 24px 72px rgba(15, 23, 42, 0.28);
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .aec-popup-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 18px;
        border-radius: 10px;
        background: {{ $accentColor }};
        color: #ffffff;
        font-weight: 700;
        text-decoration: none;
        border: 0;
        cursor: pointer;
    }
    .aec-popup-dismiss {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 16px;
        border-radius: 10px;
        background: rgba(15, 23, 42, 0.08);
        color: {{ $textColor }};
        font-weight: 600;
        border: 0;
        cursor: pointer;
    }
    .aec-popup-close {
        position: absolute;
        right: 12px;
        top: 12px;
        z-index: 2;
        border: 0;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        background: rgba(15, 23, 42, 0.82);
        color: #ffffff;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
    }
    @if($animation === 'fade')
        .aec-popup-card { animation: aecPopupFade .24s ease-out; }
        @keyframes aecPopupFade { from { opacity: 0; transform: scale(.98); } to { opacity: 1; transform: scale(1); } }
    @elseif($animation === 'slide')
        .aec-popup-card { animation: aecPopupSlide .28s ease-out; }
        @keyframes aecPopupSlide { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @endif
    @if($respectReducedMotion)
        @media (prefers-reduced-motion: reduce) {
            .aec-popup-card { animation: none !important; transition: none !important; }
        }
    @endif
    @if($hideOnMobile)
        @media (max-width: 767px) { #visitor-promo-overlay { display: none !important; } }
    @endif
    @if($hideOnDesktop)
        @media (min-width: 768px) { #visitor-promo-overlay { display: none !important; } }
    @endif
</style>

<div id="visitor-promo-overlay"
     class="aec-popup-root"
     role="{{ $isModal ? 'dialog' : 'region' }}"
     @if($isModal) aria-modal="true" @endif
     aria-labelledby="visitor-promo-title"
     aria-describedby="visitor-promo-description"
     data-popup-id="{{ $p['id'] ?? '' }}"
     data-frequency="{{ $p['frequency'] ?? 'once_per_session' }}"
     data-timezone="{{ $p['timezone'] ?? 'UTC' }}"
     data-delay="{{ (int) ($p['delay_seconds'] ?? 0) }}"
     data-scroll-percent="{{ (int) ($p['scroll_trigger_percent'] ?? 0) }}"
     data-cooldown-hours="{{ (int) ($p['cooldown_hours'] ?? 0) }}"
     data-dismissible="{{ $dismissible ? '1' : '0' }}"
     data-layout="{{ $layout }}"
     data-track-url="{{ $isPreview ? '' : ($p['track_url'] ?? '') }}"
     data-preview="{{ $isPreview ? '1' : '0' }}"
     hidden>
    <section class="aec-popup-card">
        @if($showClose)
            <button id="visitor-promo-close" class="aec-popup-close" type="button" aria-label="{{ __('Close popup') }}">&times;</button>
        @endif

        @if(! empty($p['desktop_image_url']) || ! empty($p['mobile_image_url']))
            <picture>
                @if(! empty($p['mobile_image_url']))
                    <source media="(max-width: 767px)" srcset="{{ $p['mobile_image_url'] }}">
                @endif
                <img src="{{ $p['desktop_image_url'] ?: $p['mobile_image_url'] }}"
                     alt="{{ $p['title'] ?? '' }}"
                     style="display:block;width:100%;max-height:{{ $layout === 'announcement_strip' ? '120px' : '290px' }};object-fit:cover;">
            </picture>
        @endif

        <div style="padding:{{ $layout === 'announcement_strip' ? '14px 48px 14px 20px' : '24px' }};{{ $layout === 'announcement_strip' ? 'display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;' : '' }}">
            <div style="{{ $layout === 'announcement_strip' ? 'flex:1;min-width:220px;' : '' }}">
                <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                <h2 id="visitor-promo-title" style="margin:10px 0 8px;color:{{ $textColor }};">{{ $p['title'] ?? '' }}</h2>
                @if(! empty($p['rich_html']))
                    <div id="visitor-promo-description" style="line-height:1.65;color:{{ $textColor }};">{!! $p['rich_html'] !!}</div>
                @elseif(! empty($p['body']))
                    <p id="visitor-promo-description" style="margin:0;white-space:pre-line;line-height:1.65;color:{{ $textColor }};">{{ $p['body'] }}</p>
                @else
                    <p id="visitor-promo-description" class="sr-only">{{ $p['title'] ?? '' }}</p>
                @endif
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;{{ $layout === 'announcement_strip' ? '' : 'margin-top:18px;' }}">
                @if(! empty($p['button_url']))
                    <a id="visitor-promo-cta"
                       class="aec-popup-cta"
                       href="{{ $p['button_url'] }}"
                       target="{{ ! empty($p['button_external']) ? '_blank' : '_self' }}"
                       rel="{{ ! empty($p['button_external']) || ($p['popup_type'] ?? '') === 'ad' ? 'sponsored noopener noreferrer' : 'noopener noreferrer' }}">
                        {{ $p['button_label'] }}
                    </a>
                @endif
                @if($dismissible)
                    <button id="visitor-promo-dismiss" type="button" class="aec-popup-dismiss">{{ __('Maybe later') }}</button>
                @endif
            </div>
        </div>
    </section>
</div>

<script>
    (function () {
        const overlay = document.getElementById('visitor-promo-overlay');
        if (!overlay) return;

        const isPreview = overlay.dataset.preview === '1';
        const id = overlay.dataset.popupId;
        const frequency = overlay.dataset.frequency || 'once_per_session';
        const campaignTimezone = overlay.dataset.timezone || 'UTC';
        const campaignDayKey = (() => {
            try {
                const parts = new Intl.DateTimeFormat('en-US', {
                    timeZone: campaignTimezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }).formatToParts(new Date());
                const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
                return `${values.year}-${values.month}-${values.day}`;
            } catch (e) {
                return new Date().toISOString().slice(0, 10);
            }
        })();
        const delayMs = Math.max(0, Math.min(60, Number(overlay.dataset.delay || 0))) * 1000;
        const scrollPct = Math.max(0, Math.min(100, Number(overlay.dataset.scrollPercent || 0)));
        const cooldownHours = Math.max(0, Number(overlay.dataset.cooldownHours || 0));
        const dismissible = overlay.dataset.dismissible !== '0';
        const isModal = (overlay.dataset.layout || 'modal') === 'modal';
        const trackUrl = overlay.dataset.trackUrl || '';
        const key = 'aec-popup-' + id;
        const cooldownKey = 'aec-popup-cooldown-' + id;
        const now = Date.now();

        const sendEvent = (eventName) => {
            if (isPreview || !trackUrl) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                fetch(trackUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrf ? {'X-CSRF-TOKEN': csrf} : {}),
                    },
                    body: JSON.stringify({ event: eventName }),
                    keepalive: true,
                }).catch(() => {});
            } catch (e) {}
        };

        if (!isPreview) {
            let shouldShow = true;
            try {
                if (cooldownHours > 0 && Number(localStorage.getItem(cooldownKey) || 0) > now) shouldShow = false;
                if (frequency === 'once_per_session' && sessionStorage.getItem(key)) shouldShow = false;
                if (frequency === 'once_per_day' && localStorage.getItem(key) === campaignDayKey) shouldShow = false;
                if (frequency === 'once_per_browser' && localStorage.getItem(key)) shouldShow = false;
            } catch (e) {}
            if (!shouldShow) return;
        }

        let shown = false;
        const markShown = () => {
            if (isPreview) return;
            try {
                if (frequency === 'once_per_session') sessionStorage.setItem(key, '1');
                if (frequency === 'once_per_day') localStorage.setItem(key, campaignDayKey);
                if (frequency === 'once_per_browser') localStorage.setItem(key, '1');
            } catch (e) {}
        };

        const close = (trackDismissal) => {
            overlay.hidden = true;
            overlay.style.display = 'none';
            if (isModal) document.body.style.overflow = '';
            if (!isPreview && cooldownHours > 0) {
                try { localStorage.setItem(cooldownKey, String(Date.now() + cooldownHours * 3600000)); } catch (e) {}
            }
            if (trackDismissal) sendEvent('dismissal');
        };

        const open = () => {
            if (shown) return;
            shown = true;
            overlay.hidden = false;
            overlay.style.display = isModal ? 'flex' : (overlay.dataset.layout === 'corner_card' ? 'block' : 'flex');
            if (isModal) document.body.style.overflow = 'hidden';
            markShown();
            sendEvent('impression');
        };

        document.getElementById('visitor-promo-close')?.addEventListener('click', () => close(true));
        document.getElementById('visitor-promo-dismiss')?.addEventListener('click', () => close(true));
        document.getElementById('visitor-promo-cta')?.addEventListener('click', () => sendEvent('click'));

        if (dismissible) {
            overlay.addEventListener('click', (event) => { if (event.target === overlay) close(true); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !overlay.hidden) close(true); });
        }

        if (isPreview) {
            open();
            window.reopenPopupPreview = () => { shown = false; open(); };
            return;
        }

        const checkScrollAndOpen = () => {
            if (shown) return;
            if (scrollPct <= 0) { open(); return; }
            const docHeight = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
            const currentPct = (window.scrollY / docHeight) * 100;
            if (currentPct >= scrollPct) {
                window.removeEventListener('scroll', checkScrollAndOpen);
                open();
            }
        };

        window.setTimeout(() => {
            if (scrollPct > 0) {
                window.addEventListener('scroll', checkScrollAndOpen, { passive: true });
                checkScrollAndOpen();
            } else {
                open();
            }
        }, delayMs);
    })();
</script>
