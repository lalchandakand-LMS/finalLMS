@extends('layouts.app')

@section('title', __('Advanced AI Assistant Settings') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Optional advanced add-on') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Universal AI Assistant') }}</h1>
        <p class="muted">{{ __('The Core LMS works normally when this plugin is not installed or is disabled.') }}</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.plugins.index') }}">{{ __('Plugin Manager') }}</a>
</div>
@if($errors->any())
<div class="flash error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="panel" style="max-width:1000px;margin:0 auto 24px;">
    <form method="POST" action="{{ route('admin.ai-assistant.settings.update') }}">
        @csrf
        @method('PUT')
        <label style="display:flex;gap:10px;align-items:center;margin-bottom:18px;">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled ?? false))>
            <strong>{{ __('Enable Universal AI Assistant globally') }}</strong>
        </label>
        <div class="grid grid-2">
            <div>
                <label for="ai-provider">{{ __('Provider') }}</label>
                <select id="ai-provider" name="provider"><option value="openai" @selected(old('provider', $settings->provider ?? 'openai') === 'openai')>OpenAI</option></select>
            </div>
            <div>
                <label for="ai-model">{{ __('Model ID') }}</label>
                <input id="ai-model" name="model" maxlength="120" required value="{{ old('model', $settings->model ?? '') }}" placeholder="Enter a model supported by your provider">
            </div>
            <div>
                <label for="ai-key">{{ __('API Key') }}</label>
                <input id="ai-key" type="password" name="api_key" autocomplete="new-password" maxlength="4096" placeholder="{{ $apiKeyConfigured ? __('Saved securely — leave blank to keep current key') : __('Paste provider API key') }}">
                <small class="muted">{{ $apiKeyConfigured ? __('A key is configured and encrypted at rest.') : __('No API key configured.') }}</small>
                @if($apiKeyConfigured)
                <label style="display:flex;gap:8px;align-items:center;margin-top:8px;"><input type="checkbox" name="clear_api_key" value="1"> {{ __('Revoke saved key') }}</label>
                @endif
            </div>
            <div>
                <label for="ai-temperature">{{ __('Creativity / Temperature (0–2)') }}</label>
                <input id="ai-temperature" type="number" name="temperature" min="0" max="2" step="0.01" value="{{ old('temperature', $settings->temperature ?? 0.7) }}" required>
            </div>
            <div>
                <label for="ai-max-tokens">{{ __('Maximum output tokens (1–8192)') }}</label>
                <input id="ai-max-tokens" type="number" name="max_tokens" min="1" max="8192" value="{{ old('max_tokens', $settings->max_tokens ?? 1024) }}" required>
            </div>
            <div>
                <label for="ai-budget">{{ __('Monthly token budget per account') }}</label>
                <input id="ai-budget" type="number" name="monthly_token_budget" min="1" max="100000000" value="{{ old('monthly_token_budget', $settings->monthly_token_budget ?? '') }}" placeholder="{{ __('Leave blank for no per-account cap') }}">
            </div>
            <div>
                <label for="ai-retention">{{ __('Conversation retention (days)') }}</label>
                <input id="ai-retention" type="number" name="retention_days" min="1" max="3650" value="{{ old('retention_days', $settings->retention_days ?? 365) }}" required>
            </div>
        </div>
        <hr style="margin:24px 0;">
        <h2>{{ __('Role assistants') }}</h2>
        <p class="muted">{{ __('Each role is disabled by default. Turning one on does not grant access to private LMS data.') }}</p>
        <div class="grid grid-2">
            @foreach(['admin' => 'Admin AI', 'branch_manager' => 'Branch Manager AI', 'teacher' => 'Teacher AI', 'student' => 'Student AI', 'guardian' => 'Guardian AI', 'support_staff' => 'Support Staff AI'] as $key => $label)
            <label style="display:flex;gap:10px;align-items:center;"><input type="checkbox" name="roles[{{ $key }}]" value="1" @checked(old('roles.'.$key, $roleSettings[$key] ?? false))> {{ __($label) }}</label>
            @endforeach
        </div>
        <hr style="margin:24px 0;">
        <h2>{{ __('Data access policy') }}</h2>
        <label>{{ __('General Guidance Only — no LMS records, grades, attendance, payments, student/guardian profiles, staff or branch data are provided to the model.') }}</label>
        <input type="hidden" name="data_access" value="general_guidance_only">
        <button class="btn primary" type="submit" style="margin-top:22px;">{{ __('Save Advanced AI Settings') }}</button>
    </form>
</div>
@endsection