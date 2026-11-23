@extends('layouts.app')

@section('title', __('Board Results').' | '.config('app.name'))
@section('seo_title', __('Bangladesh Education Board Results'))
@section('seo_description', __('Find official Bangladesh SSC, HSC and equivalent examination result portals and safe result-checking guidance.'))

@section('content')
<style>
.board-hero{padding:clamp(24px,4vw,42px);border-radius:22px;color:#fff;background:linear-gradient(120deg,#102c52,#176b85 68%,#1c8a8a);margin-bottom:22px}
.board-hero h1{margin:8px 0;color:#fff;font-size:clamp(1.8rem,4vw,2.7rem)}
.board-hero p{max-width:720px;margin:0;color:#d7edf4;line-height:1.7}
.board-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.board-link-card{padding:22px;border:1px solid var(--border);border-radius:16px;background:#fff}
.board-link-card h2{font-size:1.1rem;margin:0 0 8px}
.board-link-card p{color:var(--muted);line-height:1.6}
.board-link-card a{display:inline-flex;margin-top:8px}
.board-note{margin-top:18px;padding:18px;border-radius:14px;background:var(--surface-muted,#f4f7fb);line-height:1.7}
@media(max-width:640px){.board-grid{grid-template-columns:1fr}}
</style>

<section class="board-hero">
    <span style="font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ce7d8;">{{ __('Official result resources') }}</span>
    <h1>{{ __('Bangladesh Education Board Results') }}</h1>
    <p>{{ __('Use the official result services to check SSC, HSC, Dakhil, Alim and equivalent public examination results. Alom’s English Center does not collect your board roll or registration number on this page.') }}</p>
</section>

<div class="board-grid">
    <article class="board-link-card">
        <h2>{{ __('Education Board Results') }}</h2>
        <p>{{ __('Government-hosted result service for individual and institution examination results.') }}</p>
        <a class="btn primary" href="https://www.educationboardresults.gov.bd/v2/home" target="_blank" rel="noopener noreferrer">{{ __('Open official result portal') }}</a>
    </article>
    <article class="board-link-card">
        <h2>{{ __('Web Based Result Publication System') }}</h2>
        <p>{{ __('Alternative web-based result service for public examinations and available detailed results.') }}</p>
        <a class="btn primary" href="https://eboardresults.com/v2/home" target="_blank" rel="noopener noreferrer">{{ __('Open web-based result portal') }}</a>
    </article>
    <article class="board-link-card">
        <h2>{{ __('Education Boards Common Website') }}</h2>
        <p>{{ __('Check official notices and result-related announcements from the education boards.') }}</p>
        <a class="btn secondary" href="https://eduboardresults.gov.bd/" target="_blank" rel="noopener noreferrer">{{ __('Visit common education board website') }}</a>
    </article>
    <article class="board-link-card">
        <h2>{{ __('Before checking your result') }}</h2>
        <p>{{ __('Keep your examination, board, year, roll and registration details ready. Complete the verification shown by the official portal.') }}</p>
        <p class="muted">{{ __('Never share your account password, OTP or mobile banking PIN to view an examination result.') }}</p>
    </article>
</div>

<div class="board-note">
    <strong>{{ __('SMS result service') }}</strong>
    <p style="margin:6px 0 0;">{{ __('SMS formats and short codes can differ by examination and board. Follow the latest notice published by your education board before sending an SMS. Standard SMS charges may apply.') }}</p>
    <p style="margin:10px 0 0;">{{ __('If an official result portal is busy on result day, wait a little and try again rather than entering your details on an unfamiliar website.') }}</p>
</div>
@endsection
