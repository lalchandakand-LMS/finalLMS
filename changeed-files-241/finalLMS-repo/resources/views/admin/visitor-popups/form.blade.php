@extends('layouts.app')
@section('title', $popup->exists ? __('Edit popup') : __('Create popup'))
@section('content')
<div class="page-header"><div><h1>{{ $popup->exists ? __('Edit popup') : __('Create popup') }}</h1><p class="muted">{{ __('Only plain text and selected media are shown; HTML and scripts cannot be entered.') }}</p></div><a class="btn secondary" href="{{ route('admin.visitor-popups.index') }}">{{ __('Back') }}</a></div>
<div class="panel"><form method="POST" action="{{ $popup->exists ? route('admin.visitor-popups.update', $popup) : route('admin.visitor-popups.store') }}">@csrf @if($popup->exists)@method('PUT')@endif
<label>{{ __('Internal campaign title / visitor heading') }}<input name="title" maxlength="180" value="{{ old('title', $popup->title) }}" required></label>
<label>{{ __('Message / offer details') }}<textarea name="body" rows="5" maxlength="3000">{{ old('body', $popup->body) }}</textarea></label>
<div class="grid grid-2">
<label>{{ __('Popup purpose') }}<select name="popup_type">@foreach(['offer' => 'Offer', 'banner' => 'Banner', 'post' => 'Post promotion', 'ad' => 'Advertisement', 'registration' => 'Registration prompt'] as $value => $label)<option value="{{ $value }}" @selected(old('popup_type', $popup->popup_type) === $value)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Visitor audience') }}<select name="audience">@foreach(['all' => 'All visitors', 'guests' => 'Guests only', 'members' => 'Logged-in members'] as $value => $label)<option value="{{ $value }}" @selected(old('audience', $popup->audience) === $value)>{{ __($label) }}</option>@endforeach</select></label>
</div>
<label>{{ __('Banner / post / ad image (Media Library)') }}<select name="image_media_id"><option value="">{{ __('No image') }}</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected(old('image_media_id', $popup->image_media_id) == $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
<div class="grid grid-2">
<label>{{ __('Button text') }}<input name="button_label" maxlength="60" value="{{ old('button_label', $popup->button_label) }}" placeholder="{{ __('e.g. View offer / Register now') }}"></label>
<label>{{ __('Button destination') }}<input name="button_url" maxlength="500" value="{{ old('button_url', $popup->button_url) }}" placeholder="/register or https://example.com"></label>
</div>
<div class="grid grid-2">
<label>{{ __('Display frequency') }}<select name="frequency">@foreach(['every_page' => 'Every page view', 'once_per_session' => 'Once per browser session', 'once_per_day' => 'Once per day', 'once_per_browser' => 'Once per browser (until cleared)'] as $value => $label)<option value="{{ $value }}" @selected(old('frequency', $popup->frequency) === $value)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Popup delay (seconds)') }}<input type="number" name="delay_seconds" min="0" max="60" value="{{ old('delay_seconds', $popup->delay_seconds ?? 1) }}" required></label>
</div>
<div class="grid grid-2">
<label>{{ __('Start date/time') }}<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $popup->starts_at?->format('Y-m-d\TH:i')) }}"></label>
<label>{{ __('End date/time') }}<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $popup->ends_at?->format('Y-m-d\TH:i')) }}"></label>
</div>
<label>{{ __('Priority (higher appears first)') }}<input type="number" name="priority" min="0" max="10000" value="{{ old('priority', $popup->priority ?? 0) }}"></label>
<label style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $popup->is_active))> {{ __('Activate this popup') }}</label>
<button class="btn primary" type="submit">{{ __('Save popup') }}</button>
</form></div>
@endsection
