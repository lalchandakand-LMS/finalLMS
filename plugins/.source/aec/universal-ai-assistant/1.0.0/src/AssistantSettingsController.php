<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AssistantSettingsController
{
    private const ROLES = ['admin', 'branch_manager', 'teacher', 'student', 'guardian', 'support_staff'];

    public function edit(): View
    {
        $row = DB::table('ai_assistant_settings')->where('id', 1)->first();

        return view('universal-ai-assistant::admin.settings', [
            'settings' => $row,
            'roles' => self::ROLES,
            'roleSettings' => $this->decode($row?->role_settings),
            'apiKeyConfigured' => is_string($row?->api_key_encrypted) && $row->api_key_encrypted !== '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'provider' => ['required', 'in:openai'],
            'model' => ['required', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:8192'],
            'monthly_token_budget' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['boolean'],
            'data_access' => ['required', 'in:general_guidance_only'],
        ]);

        $current = DB::table('ai_assistant_settings')->where('id', 1)->first();
        $encryptedKey = $current?->api_key_encrypted;
        if (($validated['clear_api_key'] ?? false) === true) {
            $encryptedKey = null;
        } elseif (is_string($validated['api_key'] ?? null) && trim($validated['api_key']) !== '') {
            $encryptedKey = Crypt::encryptString(trim($validated['api_key']));
        }

        $roleSettings = [];
        foreach (self::ROLES as $role) {
            $roleSettings[$role] = (bool) ($validated['roles'][$role] ?? false);
        }

        DB::table('ai_assistant_settings')->updateOrInsert(
            ['id' => 1],
            [
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'provider' => $validated['provider'],
                'model' => trim($validated['model']),
                'api_key_encrypted' => $encryptedKey,
                'temperature' => (float) $validated['temperature'],
                'max_tokens' => (int) $validated['max_tokens'],
                'monthly_token_budget' => $validated['monthly_token_budget'] ?? null,
                'retention_days' => (int) $validated['retention_days'],
                'role_settings' => json_encode($roleSettings, JSON_THROW_ON_ERROR),
                'data_access' => json_encode(['mode' => 'general_guidance_only', 'lms_records' => false], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => $current?->created_at ?? now(),
            ]
        );

        return back()->with('success', __('Advanced AI Assistant settings saved. API credentials are stored encrypted and are never displayed again.'));
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}