@extends('layouts.app')
@section('title', __('Website Builder'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin CMS') }}</span><h1>{{ __('Website Builder') }}</h1><p class="muted">{{ __('Configure up to 12 homepage sections. HTML and scripts are not accepted.') }}</p></div><a href="{{ route('landing') }}" class="btn secondary" target="_blank">{{ __('Preview website') }}</a></div>
<div class="panel"><form method="POST" action="{{ route('admin.website-builder.update') }}">@csrf
@for($i = 0; $i < 12; $i++)
@php($section = old("sections.$i", $sections[$i] ?? []))
<section class="card" style="margin-bottom:16px;">
<div class="page-header"><h3>{{ __('Section') }} {{ $i + 1 }}</h3><label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="sections[{{ $i }}][enabled]" value="1" style="width:auto;" @checked($section['enabled'] ?? false)> {{ __('Enabled') }}</label></div>
<label>{{ __('Heading') }}<input name="sections[{{ $i }}][title]" maxlength="180" value="{{ $section['title'] ?? '' }}"></label>
<label>{{ __('Text') }}<textarea name="sections[{{ $i }}][body]" rows="3" maxlength="3000">{{ $section['body'] ?? '' }}</textarea></label>
<label>{{ __('Image') }}<select name="sections[{{ $i }}][image_id]"><option value="">{{ __('No image') }}</option>@foreach($media as $asset)<option value="{{ $asset->id }}" @selected(($section['image_id'] ?? '') == $asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
<div class="grid grid-2"><div><label>{{ __('Button label') }}<input name="sections[{{ $i }}][button_label]" maxlength="60" value="{{ $section['button_label'] ?? '' }}"></label></div><div><label>{{ __('Button URL') }}<input name="sections[{{ $i }}][button_url]" type="url" maxlength="500" value="{{ $section['button_url'] ?? '' }}"></label></div></div>
</section>
@endfor
<button type="submit" class="btn primary">{{ __('Save homepage layout') }}</button>
</form></div>
@endsection
