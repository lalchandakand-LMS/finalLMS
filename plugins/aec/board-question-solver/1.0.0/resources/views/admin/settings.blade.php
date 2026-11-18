@extends('layouts.app')
@section('title', __('Board Question Solver Settings'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Board Question Solver Settings') }}</h1><p class="muted">{{ __('Provider credentials, model and shared token budget are managed in Universal AI Assistant settings.') }} <a href="{{ route('admin.ai-assistant.settings') }}">{{ __('Open AI provider settings') }}</a>.</p></div></div>
@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
<div class="panel" style="max-width:850px"><form method="POST" action="{{ route('admin.board-solver.settings.update') }}">@csrf @method('PUT')
<label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled']) style="width:auto">{{ __('Enable Board Question Solver') }}</label>
<label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="allow_image_questions" value="1" @checked($settings['allow_image_questions']) style="width:auto">{{ __('Allow question photo upload (vision-capable model required)') }}</label>
<label>{{ __('Maximum question text length') }}<input type="number" name="max_question_length" min="200" max="12000" value="{{ $settings['max_question_length'] }}" required></label>
<label>{{ __('Maximum image size (KB)') }}<input type="number" name="max_image_kb" min="256" max="5120" value="{{ $settings['max_image_kb'] }}" required></label>
<label>{{ __('Per-student daily request limit') }}<input type="number" name="daily_request_limit" min="1" max="50" value="{{ $settings['daily_request_limit'] }}" required></label>
<label>{{ __('Default answer language') }}<select name="default_language"><option value="bn" @selected($settings['default_language']==='bn')>{{ __('Bangla') }}</option><option value="en" @selected($settings['default_language']==='en')>{{ __('English') }}</option></select></label>
<button class="btn primary">{{ __('Save settings') }}</button></form></div>
<p style="margin-top:16px"><a href="{{ route('admin.board-solver.history') }}">{{ __('View recent student solver activity') }}</a></p>
@endsection
