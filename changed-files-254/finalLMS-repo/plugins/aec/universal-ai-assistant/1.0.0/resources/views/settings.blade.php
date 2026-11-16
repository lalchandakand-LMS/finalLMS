@extends('layouts.app')

@section('title', __('Universal AI Assistant Settings') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Advanced optional add-on') }}</span><h1 style="margin:12px 0 0;">{{ __('Universal AI Assistant') }}</h1><p class="muted">{{ __('Configure the provider and independently control assistant access for each LMS role.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.ai-assistant.index') }}">{{ __('Open assistant') }}</a>
</div>
@if($errors->any())
    <div class="flash error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ route('admin.ai-assistant.settings.update') }}" class="panel">
    @csrf
    @method('PUT')
    <h2>{{ __('Provider & model') }}</h2>
    <div class="form-grid">
        <div><label for="ai-model">{{ __('Model') }}</label><input id="ai-model" name="model" list="ai-model-options" value="{{ old('model', $settings['model']) }}" maxlength="120" required>
            <datalist id="ai-model-options"></datalist>
            <button class="btn secondary" id="ai-load-models" type="button" data-url="{{ $modelsUrl }}">{{ __('Load available models') }}</button>
            <small id="ai-model-status" class="muted" role="status">{{ __('Save the provider key first, then load the models available to that key.') }}</small></div>
        <div><label for="ai-base-url">{{ __('Provider API base URL') }}</label><input id="ai-base-url" name="base_url" type="url" value="{{ old('base_url', $settings['base_url']) }}" required><small class="muted">{{ __('HTTPS and a server allow-listed host are required.') }}</small></div>
        <div class="full-width">
            <label for="ai-api-key">{{ __('API key') }}</label>
            <input id="ai-api-key" name="api_key" type="password" autocomplete="new-password" placeholder="{{ $settings['has_api_key'] ? __('Saved securely — enter only to rotate') : __('Enter provider API key') }}">
            <small class="muted">{{ $settings['has_api_key'] ? __('A key is saved and encrypted. It is never displayed again.') : __('No provider key is saved.') }}</small>
            <label><input type="checkbox" name="revoke_api_key" value="1"> {{ __('Revoke saved API key') }}</label>
        </div>
        <div><label for="ai-temperature">{{ __('Creativity / Temperature (0–2)') }}</label><input id="ai-temperature" name="temperature" type="number" min="0" max="2" step="0.1" value="{{ old('temperature', $settings['temperature']) }}" required></div>
        <div><label for="ai-max-tokens">{{ __('Maximum output tokens') }}</label><input id="ai-max-tokens" name="max_tokens" type="number" min="1" max="8192" value="{{ old('max_tokens', $settings['max_tokens']) }}" required></div>
        <div><label for="ai-daily-requests">{{ __('Daily request limit per user') }}</label><input id="ai-daily-requests" name="daily_request_limit" type="number" min="1" max="500" value="{{ old('daily_request_limit', $settings['daily_request_limit']) }}" required></div>
        <div><label for="ai-daily-tokens">{{ __('Institution daily token budget') }}</label><input id="ai-daily-tokens" name="daily_token_budget" type="number" min="1000" max="5000000" value="{{ old('daily_token_budget', $settings['daily_token_budget']) }}" required></div>
        <div><label for="ai-retention">{{ __('Conversation retention (days)') }}</label><input id="ai-retention" name="retention_days" type="number" min="1" max="3650" value="{{ old('retention_days', $settings['retention_days']) }}" required><small class="muted">{{ __('Expired conversations and their messages are automatically purged when the assistant is opened or used.') }}</small></div>
    </div>
    <hr>
    <h2>{{ __('Assistant access') }}</h2>
    <label><input type="checkbox" name="global_enabled" value="1" @checked(old('global_enabled', $settings['global_enabled']))> {{ __('Enable Universal AI Assistant globally') }}</label>
    <p class="muted">{{ __('All role assistants remain off unless individually enabled below. Turning this off immediately disables every assistant.') }}</p>
    <div class="grid grid-2">
        @foreach($roles as $role)
            <label class="panel" style="display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="role_enabled[{{ $role }}]" value="1" @checked(old('role_enabled.'.$role, $settings['role_enabled'][$role] ?? false))>
                <span>{{ __(ucwords(str_replace('_', ' ', $role)).' AI') }}</span>
            </label>
        @endforeach
    </div>
    <hr>
    <h2>{{ __('Data access policy') }}</h2>
    <p><strong>{{ __('General Guidance Only') }}</strong></p>
    <p class="muted">{{ __('Private LMS data access is disabled and cannot be enabled in this release. The assistant receives only the conversation text. Student records, grades, attendance, payments, guardian links, staff and branch data are never retrieved.') }}</p>
    <button class="btn primary" type="submit">{{ __('Save AI settings') }}</button>
</form>
<script>
(() => {
    const button = document.getElementById('ai-load-models');
    const input = document.getElementById('ai-model');
    const list = document.getElementById('ai-model-options');
    const status = document.getElementById('ai-model-status');
    if (!button || !input || !list || !status) return;
    button.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = 'Loading models…';
        try {
            const response = await fetch(button.dataset.url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to load models.');
            list.replaceChildren();
            const models = Array.isArray(data.models) ? data.models : [];
            models.forEach((model) => {
                const option = document.createElement('option');
                option.value = model;
                list.appendChild(option);
            });
            status.textContent = models.length ? 'Loaded ' + models.length + ' available models.' : 'No models were returned by the provider.';
        } catch (error) {
            status.textContent = error.message || 'Unable to load models.';
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
@endsection
