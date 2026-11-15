<?php

namespace Aloms\UniversalAiAssistant;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class AdminSettingsController
{
    public function index(): View
    {
        return view('universal-ai-assistant::settings', [
            'settings' => AssistantSettings::publicSettings(),
            'roles' => AssistantSettings::ROLES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'model' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:8192'],
            'global_enabled' => ['nullable', 'boolean'],
            'daily_request_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'daily_token_budget' => ['required', 'integer', 'min:1000', 'max:5000000'],
            'revoke_api_key' => ['nullable', 'boolean'],
        ];
        foreach (AssistantSettings::ROLES as $role) $rules['role_enabled.'.$role] = ['nullable', 'boolean'];

        $validated = Validator::make($request->all(), $rules)->validate();
        $host = strtolower((string) parse_url($validated['base_url'], PHP_URL_HOST));
        $allowedHosts = config('services.ai_developer.allowed_hosts', ['api.openai.com']);
        if (is_string($allowedHosts)) $allowedHosts = explode(',', $allowedHosts);
        $allowedHosts = array_map(static fn ($item) => strtolower(trim((string) $item)), is_array($allowedHosts) ? $allowedHosts : []);
        $scheme = strtolower((string) parse_url($validated['base_url'], PHP_URL_SCHEME));
        if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true)) {
            return back()->withInput($request->except('api_key'))->withErrors([
                'base_url' => __('Use HTTPS and a provider hostname explicitly allow-listed by the server.'),
            ]);
        }

        $roles = [];
        foreach (AssistantSettings::ROLES as $role) $roles[$role] = (bool) ($validated['role_enabled'][$role] ?? false);

        AssistantSettings::save([
            'model' => $validated['model'],
            'base_url' => $validated['base_url'],
            'temperature' => (float) $validated['temperature'],
            'max_tokens' => (int) $validated['max_tokens'],
            'global_enabled' => (bool) ($validated['global_enabled'] ?? false),
            'role_enabled' => $roles,
            'daily_request_limit' => (int) $validated['daily_request_limit'],
            'daily_token_budget' => (int) $validated['daily_token_budget'],
        ], $validated['api_key'] ?? null, (bool) ($validated['revoke_api_key'] ?? false));

        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'admin.ai_assistant.settings_updated',
                'description' => 'Updated Universal AI Assistant settings; provider credentials were redacted.',
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
            // Do not expose provider credentials if activity logging fails.
        }

        return redirect()->route('admin.ai-assistant.settings')->with('success', __('AI Assistant settings saved. Provider credentials are encrypted and never shown again.'));
    }
}
