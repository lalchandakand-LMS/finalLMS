@extends('layouts.app')

@section('title', $popup->exists ? __('Edit popup') : __('Create popup'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $popup->exists ? __('Edit popup') : __('Create popup') }}</h1>
        <p class="muted">{{ __('Configure campaign content, layout, responsive media, targeting rules, schedule windows and consent-aware analytics.') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($popup->exists)
            <a class="btn secondary" href="{{ route('admin.visitor-popups.preview', $popup->id) }}">{{ __('Preview') }}</a>
        @endif
        <a class="btn secondary" href="{{ route('admin.visitor-popups.index') }}">{{ __('Back') }}</a>
    </div>
</div>

@if($errors->any())
    <div class="flash error" role="alert">
        <strong>{{ __('Please correct the following:') }}</strong>
        <ul style="margin:8px 0 0;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="panel">
    <form method="POST"
          action="{{ $popup->exists ? route('admin.visitor-popups.update', $popup->id) : route('admin.visitor-popups.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($popup->exists) @method('PUT') @endif

        <h3>{{ __('1. Content & Call-to-Action') }}</h3>
        <label>{{ __('Internal campaign title / visitor heading') }}
            <input name="title" maxlength="180" value="{{ old('title', $popup->title) }}" required>
        </label>

        <div class="grid grid-2">
            <label>{{ __('Popup purpose') }}
                <select name="popup_type">
                    @foreach(['offer' => 'Offer', 'banner' => 'Banner', 'announcement' => 'Announcement', 'post' => 'Post promotion', 'ad' => 'Advertisement', 'registration' => 'Registration prompt'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('popup_type', $popup->popup_type) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Content mode') }}
                <select name="content_mode">
                    @foreach(['mixed' => 'Image + plain text', 'image_only' => 'Image only', 'text_only' => 'Text only', 'sanitized_html' => 'Sanitized rich HTML'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_mode', $popup->content_mode ?: 'mixed') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label>{{ __('Message / offer details') }}
            <textarea name="body" rows="4" maxlength="3000">{{ old('body', $popup->body) }}</textarea>
        </label>

        <label>{{ __('Sanitized rich HTML (used only in Sanitized rich HTML mode)') }}
            <textarea name="rich_html" rows="4" maxlength="8000" placeholder="<p><strong>Save 20%</strong> on Spoken English batches.</p>">{{ old('rich_html', $popup->rich_html) }}</textarea>
            <small class="muted">{{ __('Allowed tags: p, br, strong, b, em, i, u, ul, ol, li, h3, h4, blockquote, span, a (HTTPS or relative paths only). Scripts, styles and event handlers are stripped.') }}</small>
        </label>

        <div class="grid grid-2">
            <label>{{ __('Button text') }}
                <input name="button_label" maxlength="60" value="{{ old('button_label', $popup->button_label) }}" placeholder="{{ __('e.g. View offer / Register now') }}">
            </label>
            <label>{{ __('Button destination') }}
                <input name="button_url" maxlength="500" value="{{ old('button_url', $popup->button_url) }}" placeholder="/register or https://example.com">
            </label>
        </div>

        <hr style="margin:24px 0;border:0;border-top:1px solid var(--border);">
        <h3>{{ __('2. Artwork & Responsive Display') }}</h3>
        <div class="grid grid-2">
            <label>{{ __('Desktop image (Media Library)') }}
                <select name="image_media_id">
                    <option value="">{{ __('No image') }}</option>
                    @foreach($media as $asset)
                        <option value="{{ $asset->id }}" @selected(old('image_media_id', $popup->image_media_id) == $asset->id)>{{ $asset->original_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Or upload new desktop image (JPG/PNG/WebP/GIF, max 5MB)') }}
                <input type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
        </div>

        <div class="grid grid-2">
            <label>{{ __('Mobile image override (Media Library)') }}
                <select name="mobile_image_media_id">
                    <option value="">{{ __('Reuse desktop image') }}</option>
                    @foreach($media as $asset)
                        <option value="{{ $asset->id }}" @selected(old('mobile_image_media_id', $popup->mobile_image_media_id) == $asset->id)>{{ $asset->original_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Or upload new mobile image (JPG/PNG/WebP/GIF, max 5MB)') }}
                <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin:10px 0;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="hide_on_mobile" value="1" style="width:auto;" @checked(old('hide_on_mobile', $popup->hide_on_mobile))>
                {{ __('Hide on mobile screens') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="hide_on_desktop" value="1" style="width:auto;" @checked(old('hide_on_desktop', $popup->hide_on_desktop))>
                {{ __('Hide on desktop screens') }}
            </label>
        </div>

        <hr style="margin:24px 0;border:0;border-top:1px solid var(--border);">
        <h3>{{ __('3. Layout, Appearance & Behavior') }}</h3>
        <div class="grid grid-2">
            <label>{{ __('Popup layout') }}
                <select name="layout">
                    @foreach(['modal' => 'Centered modal dialog', 'corner_card' => 'Floating corner card', 'full_banner' => 'Full-width banner', 'announcement_strip' => 'Top/bottom announcement strip'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('layout', $popup->layout ?: 'modal') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Placement') }}
                <select name="placement">
                    @foreach(['center' => 'Center', 'bottom_right' => 'Bottom right', 'bottom_left' => 'Bottom left', 'top' => 'Top edge', 'bottom' => 'Bottom edge'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('placement', $popup->placement ?: 'center') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid grid-2">
            <label>{{ __('Max width (px)') }}
                <input type="number" name="max_width_px" min="280" max="1200" value="{{ old('max_width_px', $popup->max_width_px ?? 620) }}">
            </label>
            <label>{{ __('Typography scale') }}
                <select name="font_scale">
                    @foreach(['compact' => 'Compact', 'normal' => 'Normal', 'large' => 'Large'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('font_scale', $popup->font_scale ?: 'normal') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
            <label>{{ __('Background color') }}
                <input type="text" name="background_color" maxlength="7" value="{{ old('background_color', $popup->background_color ?: '#ffffff') }}">
            </label>
            <label>{{ __('Text color') }}
                <input type="text" name="text_color" maxlength="7" value="{{ old('text_color', $popup->text_color ?: '#14213d') }}">
            </label>
            <label>{{ __('Accent / button color') }}
                <input type="text" name="accent_color" maxlength="7" value="{{ old('accent_color', $popup->accent_color ?: '#1546a8') }}">
            </label>
            <label>{{ __('Overlay opacity (%)') }}
                <input type="number" name="overlay_opacity" min="0" max="95" value="{{ old('overlay_opacity', $popup->overlay_opacity ?? 70) }}">
            </label>
            <label>{{ __('Animation style') }}
                <select name="animation_style">
                    @foreach(['fade' => 'Fade', 'slide' => 'Slide', 'none' => 'None'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('animation_style', $popup->animation_style ?: 'fade') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin:10px 0;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="show_close_button" value="1" style="width:auto;" @checked(old('show_close_button', $popup->show_close_button ?? true))>
                {{ __('Show close (X) button') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="dismissible" value="1" style="width:auto;" @checked(old('dismissible', $popup->dismissible ?? true))>
                {{ __('Allow backdrop / Escape dismissal') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="respect_reduced_motion" value="1" style="width:auto;" @checked(old('respect_reduced_motion', $popup->respect_reduced_motion ?? true))>
                {{ __('Respect prefers-reduced-motion') }}
            </label>
        </div>

        <hr style="margin:24px 0;border:0;border-top:1px solid var(--border);">
        <h3>{{ __('4. Targeting Rules') }}</h3>
        <div class="grid grid-2">
            <label>{{ __('Visitor audience') }}
                <select name="audience">
                    @foreach(['all' => 'All visitors', 'guests' => 'Guests only', 'members' => 'Logged-in members'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience', $popup->audience) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Target URL path patterns (one per line, prefix ! to exclude)') }}
                <textarea name="target_paths" rows="3" placeholder="/&#10;/courses*&#10;!/register">{{ old('target_paths', $popup->target_paths) }}</textarea>
            </label>
        </div>

        @php
            $selectedRoles = old('target_roles', $popup->target_roles ?? []);
            $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [];
        @endphp
        <div style="margin:10px 0;">
            <span style="display:block;font-weight:600;margin-bottom:6px;">{{ __('Target specific roles (leave unchecked for any)') }}</span>
            <div style="display:flex;gap:14px;flex-wrap:wrap;">
                @foreach(['guest' => 'Guest', 'student' => 'Student', 'teacher' => 'Teacher', 'guardian' => 'Guardian', 'branch_manager' => 'Branch Manager', 'accountant' => 'Accountant'] as $roleValue => $roleLabel)
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                        <input type="checkbox" name="target_roles[]" value="{{ $roleValue }}" style="width:auto;" @checked(in_array($roleValue, $selectedRoles, true))>
                        {{ __($roleLabel) }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:10px;">
            <label>{{ __('Target branch (optional)') }}
                <select name="target_branch_id">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('target_branch_id', $popup->target_branch_id) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Target course (optional)') }}
                <select name="target_course_id">
                    <option value="">{{ __('All courses') }}</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('target_course_id', $popup->target_course_id) == $course->id)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Target batch (optional)') }}
                <select name="target_batch_id">
                    <option value="">{{ __('All batches') }}</option>
                    @foreach($batches as $batch)
                        <option value="{{ $batch->id }}" @selected(old('target_batch_id', $popup->target_batch_id) == $batch->id)>{{ $batch->title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <hr style="margin:24px 0;border:0;border-top:1px solid var(--border);">
        <h3>{{ __('5. Frequency, Schedule & Analytics') }}</h3>
        <div class="grid grid-2">
            <label>{{ __('Display frequency') }}
                <select name="frequency">
                    @foreach(['every_page' => 'Every page view', 'once_per_session' => 'Once per browser session', 'once_per_day' => 'Once per day', 'once_per_browser' => 'Once per browser (until cleared)'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('frequency', $popup->frequency) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Popup delay (seconds)') }}
                <input type="number" name="delay_seconds" min="0" max="60" value="{{ old('delay_seconds', $popup->delay_seconds ?? 1) }}" required>
            </label>
        </div>

        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <label>{{ __('Scroll trigger (%)') }}
                <input type="number" name="scroll_trigger_percent" min="0" max="100" value="{{ old('scroll_trigger_percent', $popup->scroll_trigger_percent ?? 0) }}">
            </label>
            <label>{{ __('Cooldown after dismiss (hours)') }}
                <input type="number" name="cooldown_hours" min="0" max="8760" value="{{ old('cooldown_hours', $popup->cooldown_hours ?? 0) }}">
            </label>
            <label>{{ __('Max total impressions (optional)') }}
                <input type="number" name="max_impressions" min="1" max="10000000" value="{{ old('max_impressions', $popup->max_impressions) }}">
            </label>
            <label>{{ __('Priority (higher appears first)') }}
                <input type="number" name="priority" min="0" max="10000" value="{{ old('priority', $popup->priority ?? 0) }}">
            </label>
        </div>

        @php
            $tz = old('timezone', $popup->timezone ?: config('app.timezone', 'UTC'));
        @endphp
        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:10px;">
            <label>{{ __('Campaign status') }}
                <select name="status">
                    @foreach(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $popup->status ?: ($popup->is_active ? 'active' : 'draft')) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Schedule timezone') }}
                <select name="timezone">
                    @foreach($timezones as $timezoneOption)
                        <option value="{{ $timezoneOption }}" @selected($tz === $timezoneOption)>{{ $timezoneOption }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('Start date/time') }}
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $popup->starts_at?->copy()->timezone($tz)->format('Y-m-d\TH:i')) }}">
            </label>
            <label>{{ __('End date/time') }}
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $popup->ends_at?->copy()->timezone($tz)->format('Y-m-d\TH:i')) }}">
            </label>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin:14px 0;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="analytics_enabled" value="1" style="width:auto;" @checked(old('analytics_enabled', $popup->analytics_enabled ?? true))>
                {{ __('Record aggregate impressions & clicks') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="require_consent_for_analytics" value="1" style="width:auto;" @checked(old('require_consent_for_analytics', $popup->require_consent_for_analytics ?? true))>
                {{ __('Require marketing cookie consent before recording analytics') }}
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $popup->is_active))>
                {{ __('Activate this popup') }}
            </label>
        </div>

        <button class="btn primary" type="submit">{{ __('Save popup') }}</button>
    </form>
</div>
@endsection
