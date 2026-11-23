@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4"><h1 class="h3 mb-1">AI Agent Settings</h1><p class="text-muted mb-0">Configure the provider, usage limits and role permissions. API keys are encrypted and never displayed again.</p></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.ai-assistant.settings.update') }}" class="card shadow-sm">
        @csrf @method('PUT')
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Model</label><input class="form-control" name="model" value="{{ old('model', $settings['model']) }}" required maxlength="120"></div>
                <div class="col-md-6"><label class="form-label">Provider API base URL</label><input class="form-control" type="url" name="base_url" value="{{ old('base_url', $settings['base_url']) }}" required></div>
                <div class="col-md-8"><label class="form-label">API key {{ $settings['has_api_key'] ? '(saved — leave blank to keep)' : '' }}</label><input class="form-control" type="password" name="api_key" autocomplete="new-password" maxlength="500"></div>
                <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="revoke_api_key" value="1"> Revoke saved key</label></div>
                <div class="col-md-3"><label class="form-label">Temperature</label><input class="form-control" type="number" step="0.01" min="0" max="2" name="temperature" value="{{ old('temperature', $settings['temperature']) }}" required></div>
                <div class="col-md-3"><label class="form-label">Max output tokens</label><input class="form-control" type="number" min="1" max="8192" name="max_tokens" value="{{ old('max_tokens', $settings['max_tokens']) }}" required></div>
                <div class="col-md-3"><label class="form-label">Daily requests / user</label><input class="form-control" type="number" min="1" max="500" name="daily_request_limit" value="{{ old('daily_request_limit', $settings['daily_request_limit']) }}" required></div>
                <div class="col-md-3"><label class="form-label">Daily token budget</label><input class="form-control" type="number" min="1000" max="5000000" name="daily_token_budget" value="{{ old('daily_token_budget', $settings['daily_token_budget']) }}" required></div>
                <div class="col-md-3"><label class="form-label">Conversation retention (days)</label><input class="form-control" type="number" min="1" max="3650" name="retention_days" value="{{ old('retention_days', $settings['retention_days']) }}" required></div>
            </div>
            <hr><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="global_enabled" value="1" id="global_enabled" @checked(old('global_enabled', $settings['global_enabled']))><label class="form-check-label fw-semibold" for="global_enabled">Enable AI assistant globally</label></div>
            <h2 class="h6">Role access</h2>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Role</th><th>Enable agent</th><th>Allow scoped LMS context</th></tr></thead><tbody>
            @foreach($roles as $role)
                <tr><td>{{ str($role)->replace('_',' ')->title() }}</td><td><input class="form-check-input" type="checkbox" name="role_enabled[{{ $role }}]" value="1" @checked(old('role_enabled.'.$role, $settings['role_enabled'][$role] ?? false))></td><td><input class="form-check-input" type="checkbox" name="role_data_access[{{ $role }}]" value="1" @checked(old('role_data_access.'.$role, $settings['role_data_access'][$role] ?? false)) @disabled($role === 'support_staff')></td></tr>
            @endforeach
            </tbody></table></div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center"><span class="small text-muted">Model availability and provider hostname are validated server-side.</span><button class="btn btn-primary" type="submit">Save AI settings</button></div>
    </form>
</div>
@endsection