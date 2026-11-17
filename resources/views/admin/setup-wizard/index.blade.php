@extends('layouts.app')

@section('title', __('Initial setup') . ' | ' . config('app.name'))

@section('content')
@php
    $completed = !empty($settings['setup_wizard_completed_at']);
    $steps = [1 => __('Institution profile'), 2 => __('Academic year'), 3 => __('Payments')];
@endphp
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Getting started') }}</span>
        <h1 style="margin:12px 0 0">{{ __('Initial setup wizard') }}</h1>
        <p class="muted">{{ __('Configure the essentials for this institution. Your progress is saved after every step and you can return later.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
</div>

@if(session('success') || session('status'))
    <div class="panel" style="margin-bottom:16px">{{ session('success') ?? session('status') }}</div>
@endif
@if($errors->any())
    <div class="panel" style="margin-bottom:16px;color:#a11">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
@endif

<div class="panel" style="max-width:1000px;margin:0 auto">
    <div class="grid grid-3" style="margin-bottom:24px">
        @foreach($steps as $number => $label)
            <div class="panel" style="padding:14px;border:1px solid {{ $step === $number ? 'var(--primary)' : 'var(--border)' }}">
                <strong>{{ $number }}. {{ $label }}</strong>
                <div class="muted">{{ $completed || (int)($settings['setup_wizard_step'] ?? 1) > $number ? __('Saved') : ($step === $number ? __('Current step') : __('Not started')) }}</div>
            </div>
        @endforeach
    </div>

    @if($completed)
        <h2>{{ __('Your initial setup is complete') }}</h2>
        <p class="muted">{{ __('You can update institution identity and language from Website & Institution Settings. Payment and communication integrations can be configured whenever you are ready.') }}</p>
        <a class="btn primary" href="{{ route('admin.settings.index') }}">{{ __('Open institution settings') }}</a>
    @else
        <form method="POST" action="{{ route('admin.setup-wizard.update') }}">
            @csrf
            <input type="hidden" name="step" value="{{ $step }}">

            @if($step === 1)
                <h2>{{ __('Institution profile and language') }}</h2>
                <div class="grid grid-2">
                    <div><label>{{ __('Institution name') }}</label><input name="institution_name" required maxlength="160" value="{{ old('institution_name', $settings['institution_name'] ?? config('app.name')) }}"></div>
                    <div><label>{{ __('Short name') }}</label><input name="institution_short_name" maxlength="60" value="{{ old('institution_short_name', $settings['institution_short_name'] ?? '') }}"></div>
                    <div><label>{{ __('Tagline') }}</label><input name="institution_tagline" maxlength="180" value="{{ old('institution_tagline', $settings['institution_tagline'] ?? '') }}"></div>
                    <div><label>{{ __('Contact email') }}</label><input type="email" name="institution_contact_email" value="{{ old('institution_contact_email', $settings['institution_contact_email'] ?? '') }}"></div>
                    <div><label>{{ __('Contact phone') }}</label><input name="institution_contact_phone" maxlength="40" value="{{ old('institution_contact_phone', $settings['institution_contact_phone'] ?? '') }}"></div>
                    <div><label>{{ __('Website') }}</label><input type="url" name="institution_website" value="{{ old('institution_website', $settings['institution_website'] ?? '') }}"></div>
                    <div><label>{{ __('Logo URL') }}</label><input type="url" name="institution_logo_url" maxlength="500" value="{{ old('institution_logo_url', $settings['institution_logo_url'] ?? '') }}"></div>
                    <div><label>{{ __('Favicon URL') }}</label><input type="url" name="institution_favicon_url" maxlength="500" value="{{ old('institution_favicon_url', $settings['institution_favicon_url'] ?? '') }}"></div>
                    <div>
                        <label for="setup-logo-media">{{ __('Logo from Media Library') }}</label>
                        <select id="setup-logo-media" name="site_logo_media_id">
                            <option value="">{{ __('Use logo URL / no uploaded logo') }}</option>
                            @foreach($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('site_logo_media_id', $settings['site_logo_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="setup-favicon-media">{{ __('Favicon from Media Library') }}</label>
                        <select id="setup-favicon-media" name="site_favicon_media_id">
                            <option value="">{{ __('Use favicon URL / no uploaded favicon') }}</option>
                            @foreach($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('site_favicon_media_id', $settings['site_favicon_media_id'] ?? '') === (string) $asset->id)>{{ $asset->original_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="grid-column:1/-1" class="muted">{{ __('Upload images in Media Library first, then select them here. A selected library image takes priority over its URL.') }} <a href="{{ route('admin.media.index') }}">{{ __('Open Media Library') }}</a></div>
                    <div><label>{{ __('Default language') }}</label><select name="institution_default_locale"><option value="en" @selected(old('institution_default_locale', $settings['institution_default_locale'] ?? 'en') === 'en')>{{ __('English') }}</option><option value="bn" @selected(old('institution_default_locale', $settings['institution_default_locale'] ?? 'en') === 'bn')>{{ __('বাংলা') }}</option></select></div>
                    <div><label>{{ __('Timezone') }}</label><select name="institution_timezone">@foreach(['Asia/Dhaka'=>'Bangladesh (Dhaka)','Asia/Kolkata'=>'India (Kolkata)','Asia/Karachi'=>'Pakistan (Karachi)','Asia/Dubai'=>'UAE (Dubai)','Europe/London'=>'United Kingdom (London)','UTC'=>'UTC'] as $zone=>$label)<option value="{{ $zone }}" @selected(old('institution_timezone', $settings['institution_timezone'] ?? 'Asia/Dhaka') === $zone)>{{ $label }}</option>@endforeach</select></div>
                    <div><label>{{ __('Primary brand color') }}</label><input type="color" name="site_primary_color" value="{{ old('site_primary_color', $settings['site_primary_color'] ?? '#1546a8') }}"></div>
                    <div style="grid-column:1/-1"><label>{{ __('Institution address') }}</label><textarea name="institution_address" rows="2" maxlength="500">{{ old('institution_address', $settings['institution_address'] ?? '') }}</textarea></div>
                </div>
            @elseif($step === 2)
                <h2>{{ __('Academic year') }}</h2>
                <p class="muted">{{ __('Set the current academic or session year. You can revise it later without deleting student or financial records.') }}</p>
                <div class="grid grid-2">
                    <div><label>{{ __('Academic year name') }}</label><input name="academic_year_name" required maxlength="80" placeholder="2026–2027" value="{{ old('academic_year_name', $settings['academic_year_name'] ?? '') }}"></div>
                    <div><label>{{ __('Start date') }}</label><input type="date" name="academic_year_start" required value="{{ old('academic_year_start', $settings['academic_year_start'] ?? '') }}"></div>
                    <div><label>{{ __('End date') }}</label><input type="date" name="academic_year_end" required value="{{ old('academic_year_end', $settings['academic_year_end'] ?? '') }}"></div>
                </div>
            @else
                <h2>{{ __('Payment methods') }}</h2>
                <p class="muted">{{ __('Enable only the methods your institution currently accepts. Add gateway API credentials later in the relevant integration settings; do not paste secret keys here.') }}</p>
                <div class="grid grid-2">
                    @foreach(['bkash'=>'bKash','rocket'=>'Rocket','bank'=>'Bank transfer','manual'=>'Manual payment'] as $method=>$label)
                        @php($values = $paymentMethods[$method])
                        <div class="panel" style="padding:16px;border:1px solid var(--border)">
                            <input type="hidden" name="payment_methods[{{ $method }}][enabled]" value="0">
                            <label><input type="checkbox" name="payment_methods[{{ $method }}][enabled]" value="1" @checked(old("payment_methods.$method.enabled", $values['enabled']))> {{ __($label) }}</label>
                            <div><label>{{ __('Account / payment details') }}</label><input name="payment_methods[{{ $method }}][account]" maxlength="120" value="{{ old("payment_methods.$method.account", $values['account']) }}"></div>
                            <div><label>{{ __('Instructions') }}</label><textarea name="payment_methods[{{ $method }}][instructions]" rows="2" maxlength="500">{{ old("payment_methods.$method.instructions", $values['instructions']) }}</textarea></div>
                        </div>
                    @endforeach
                </div>
                <p class="muted">{{ __('Financial records currently use BDT. Existing invoices and payments will not be relabelled by this wizard.') }}</p>
            @endif

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px">
                <button class="btn primary" type="submit">{{ $step === 3 ? __('Finish setup') : __('Save and continue') }}</button>
                @if($step > 1)<a class="btn secondary" href="{{ route('admin.setup-wizard.index', ['step' => $step - 1]) }}">{{ __('Previous') }}</a>@endif
            </div>
        </form>
        <form method="POST" action="{{ route('admin.setup-wizard.skip') }}" style="margin-top:12px">
            @csrf<input type="hidden" name="step" value="{{ $step }}">
            <button type="submit" class="btn secondary">{{ __('Skip this step for now') }}</button>
        </form>
    @endif
</div>
@endsection
