@extends('layouts.app')

@section('title', __('Achievement Card').' | '.config('app.name'))
@section('seo_title', __('Verified learning achievement'))
@section('seo_description', __('View and share an issued course completion achievement from Alom’s English Center.'))

@section('content')
<style>
.achievement-wrap{max-width:900px;margin:20px auto}
.achievement-card{position:relative;overflow:hidden;padding:clamp(24px,5vw,54px);border:8px double #c49b45;border-radius:22px;background:radial-gradient(circle at 10% 10%,#fff8e5,transparent 35%),linear-gradient(135deg,#fff,#f3f7ff);text-align:center;box-shadow:0 18px 55px rgba(16,42,76,.12)}
.achievement-card:before,.achievement-card:after{content:"";position:absolute;width:180px;height:180px;border:1px solid rgba(196,155,69,.35);border-radius:50%;pointer-events:none}
.achievement-card:before{top:-110px;left:-100px}
.achievement-card:after{right:-100px;bottom:-110px}
.achievement-kicker{color:#8b6826;font-size:.75rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase}
.achievement-card h1{margin:14px 0;color:#17345d;font-size:clamp(1.7rem,4vw,2.7rem)}
.achievement-student{margin:24px 0 10px;color:#102c52;font-family:Georgia,serif;font-size:clamp(1.8rem,5vw,3.2rem);font-weight:700}
.achievement-course{margin:12px 0;color:#176b85;font-size:clamp(1.15rem,3vw,1.7rem);font-weight:800}
.achievement-meta{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:28px}
.achievement-meta span{padding:8px 12px;border:1px solid #e5d5ad;border-radius:999px;color:#59451f;font-size:.82rem}
.achievement-footer{margin-top:30px;color:#53647b;font-size:.85rem}
.achievement-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:18px 0}
@media print{
    @page{size:landscape;margin:10mm}
    body{background:#fff!important}
    body *{visibility:hidden!important}
    .achievement-card,.achievement-card *{visibility:visible!important}
    .achievement-card{position:absolute;left:0;top:0;width:100%;margin:0;box-shadow:none;print-color-adjust:exact;-webkit-print-color-adjust:exact}
    .achievement-actions,.site-header,.site-footer,.public-navigation{display:none!important}
}
</style>

<div class="achievement-wrap">
    <div class="achievement-actions">
        <button class="btn primary" type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        <button class="btn secondary" type="button" id="share-achievement">{{ __('Share achievement') }}</button>
        <a class="btn secondary" href="{{ route('board-results.index') }}">{{ __('Board Results') }}</a>
    </div>

    <article class="achievement-card" id="achievement-card">
        <div class="achievement-kicker">{{ __('Achievement Award') }}</div>
        <h1>{{ $institutionName }}</h1>
        <p style="margin:0;color:#64748b;">{{ __('This achievement is awarded to') }}</p>
        <div class="achievement-student">{{ $certificate->user->name }}</div>
        <p style="margin:0;color:#64748b;">{{ __('For successfully completing the course') }}</p>
        <div class="achievement-course">{{ $certificate->course->title }}</div>
        <div class="achievement-meta">
            <span>{{ __('Certificate ID:') }} {{ $certificate->certificate_number }}</span>
            <span>{{ __('Issued:') }} {{ $certificate->issued_at?->format('F j, Y') ?? __('Date not available') }}</span>
        </div>
        <div class="achievement-footer">{{ __('Issued by') }} {{ $institutionName }} · {{ __('A verified course completion achievement') }}</div>
    </article>
</div>

<script>
(() => {
    const button = document.getElementById('share-achievement');
    if (!button) return;
    button.addEventListener('click', async () => {
        const shareData = {
            title: @json(__('Achievement Card').' | '.$institutionName),
            text: @json(__('View this course completion achievement.')),
            url: window.location.href
        };
        try {
            if (navigator.share) {
                await navigator.share(shareData);
                return;
            }
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(window.location.href);
                button.textContent = @json(__('Link copied'));
                return;
            }
            window.prompt(@json(__('Copy this achievement link')), window.location.href);
        } catch (error) {
            if (error && error.name !== 'AbortError') {
                window.prompt(@json(__('Copy this achievement link')), window.location.href);
            }
        }
    });
})();
</script>
@endsection
