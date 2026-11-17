@extends('layouts.app')
@section('title', __('Theme Preview').' — '.$theme->name)
@section('content')
<div class="page-header">
    <div><span class="badge warning">{{ __('Unpublished preview') }}</span><h1>{{ $theme->name }}</h1><p class="muted">{{ __('Version') }} {{ $theme->version }} · {{ __('Only administrators can view this preview. The live theme is unchanged.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.themes.index') }}">{{ __('Back to Theme Manager') }}</a>
</div>
<div class="panel" style="padding:0;overflow:hidden;">
    <iframe
        title="{{ __('Isolated theme preview') }}"
        src="{{ route('admin.themes.preview.frame', $theme->id) }}"
        sandbox
        referrerpolicy="no-referrer"
        loading="lazy"
        style="display:block;width:100%;min-height:760px;border:0;background:#fff;"
    ></iframe>
</div>
<p class="muted">{{ __('The preview runs in a sandboxed frame. Theme CSS cannot style or interact with the Admin Panel.') }}</p>
@endsection
