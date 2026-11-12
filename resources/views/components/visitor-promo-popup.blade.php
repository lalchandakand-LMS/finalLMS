        @php
            $popupButtonUrl = $visitorPopup->popup_type === 'registration'
                ? (auth()->check() ? route('dashboard') : route('register'))
                : ($visitorPopup->button_url ? (str_starts_with($visitorPopup->button_url, '/') ? url($visitorPopup->button_url) : $visitorPopup->button_url) : null);
            $popupButtonLabel = $visitorPopup->button_label ?: ($visitorPopup->popup_type === 'registration' ? __('Register now') : __('Learn more'));
            $popupIsExternal = $popupButtonUrl && str_starts_with($popupButtonUrl, 'https://') && !str_starts_with($popupButtonUrl, url('/'));
            $popupBadgeClass = $visitorPopup->popup_type === 'ad' ? 'warning' : ($visitorPopup->popup_type === 'registration' ? 'success' : 'info');
            $popupBadgeLabel = $visitorPopup->popup_type === 'ad' ? __('Advertisement') : ($visitorPopup->popup_type === 'registration' ? __('Join us') : ucfirst($visitorPopup->popup_type));
        @endphp
        <div id="visitor-promo-overlay" role="dialog" aria-modal="true" aria-labelledby="visitor-promo-title" aria-describedby="visitor-promo-description" data-popup-id="{{ $visitorPopup->id }}" data-frequency="{{ $visitorPopup->frequency }}" data-delay="{{ $visitorPopup->delay_seconds }}" hidden style="position:fixed;inset:0;z-index:1000;background:rgba(8,18,40,.72);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;padding:18px;">
            <section style="position:relative;width:min(620px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 28px 90px rgba(0,0,0,.35);">
                <button id="visitor-promo-close" type="button" aria-label="{{ __('Close popup') }}" style="position:absolute;right:12px;top:12px;z-index:2;border:0;border-radius:50%;width:40px;height:40px;background:rgba(15,23,42,.82);color:#fff;font-size:25px;cursor:pointer;">&times;</button>
                @if($visitorPopup->image)
                    <img src="{{ $visitorPopup->image->url }}" alt="{{ $visitorPopup->title }}" style="display:block;width:100%;max-height:290px;object-fit:cover;">
                @endif
                <div style="padding:26px;">
                    <span class="badge {{ $popupBadgeClass }}">{{ $popupBadgeLabel }}</span>
                    <h2 id="visitor-promo-title" style="margin:12px 0;color:#14213d;">{{ $visitorPopup->title }}</h2>
                    <p id="visitor-promo-description" style="white-space:pre-line;line-height:1.7;color:#4b5563;">{{ $visitorPopup->body }}</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                        @if($popupButtonUrl)
                            <a class="btn primary" href="{{ $popupButtonUrl }}" target="{{ $popupIsExternal ? '_blank' : '_self' }}" rel="{{ $popupIsExternal || $visitorPopup->popup_type === 'ad' ? 'sponsored noopener noreferrer' : 'noopener noreferrer' }}">{{ $popupButtonLabel }}</a>
                        @endif
                        <button id="visitor-promo-dismiss" type="button" class="btn secondary">{{ __('Maybe later') }}</button>
                    </div>
                </div>
            </section>
        </div>
        <script>
            (function () {
                const overlay = document.getElementById('visitor-promo-overlay');
                if (!overlay) return;
                const id = overlay.dataset.popupId;
                const frequency = overlay.dataset.frequency;
                const key = 'aec-popup-' + id;
                const now = Date.now();
                let shouldShow = true;
                try {
                    if (frequency === 'once_per_session') shouldShow = !sessionStorage.getItem(key);
                    if (frequency === 'once_per_day') shouldShow = !(Number(localStorage.getItem(key) || 0) > now);
                    if (frequency === 'once_per_browser') shouldShow = !localStorage.getItem(key);
                } catch (e) {}
                if (!shouldShow) return;
                const close = () => {
                    overlay.style.display = 'none';
                    overlay.hidden = true;
                    document.body.style.overflow = '';
                };
                const markShown = () => {
                    try {
                        if (frequency === 'once_per_session') sessionStorage.setItem(key, '1');
                        if (frequency === 'once_per_day') localStorage.setItem(key, String(now + 86400000));
                        if (frequency === 'once_per_browser') localStorage.setItem(key, '1');
                    } catch (e) {}
                };
                document.getElementById('visitor-promo-close')?.addEventListener('click', close);
                document.getElementById('visitor-promo-dismiss')?.addEventListener('click', close);
                overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
                document.addEventListener('keydown', event => { if (event.key === 'Escape' && !overlay.hidden) close(); });
                window.setTimeout(() => {
                    overlay.hidden = false;
                    overlay.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    markShown();
                }, Math.max(0, Math.min(60, Number(overlay.dataset.delay || 0))) * 1000);
            })();
        </script>
