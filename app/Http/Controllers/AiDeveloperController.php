<?php

namespace App\\Http\\Controllers;

use App\\Models\\AiDeveloperTask;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\View\\View;
use Throwable;

class AiDeveloperController extends Controller
{
    public function index(): View
    {
        $tasks = AiDeveloperTask::query()->where('user_id', auth()->id())->latest()->limit(25)->get();
        $configured = filled(config('services.ai_developer.api_key'))
            && filled(config('services.ai_developer.base_url'))
            && filled(config('services.ai_developer.model'));

        return view('admin.ai-developer.index', compact('tasks', 'configured'));
    }

    public function analyze(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instruction' => ['required', 'string', 'min:10', 'max:12000'],
        ]);

        // Sanitize before persistence as well as before forwarding data to an external provider.
        $safeTitle = $this->redactSensitiveText($validated['title']);
        $safeInstruction = $this->redactSensitiveText($validated['instruction']);

        $task = AiDeveloperTask::create([
            'user_id' => $request->user()->id,
            'title' => $safeTitle,
            'instruction' => $safeInstruction,
            'status' => 'processing',
            'provider' => config('services.ai_developer.provider', 'openai-compatible'),
            'model' => config('services.ai_developer.model'),
        ]);

        $apiKey = config('services.ai_developer.api_key');
        $baseUrl = rtrim((string) config('services.ai_developer.base_url'), '/');
        $model = config('services.ai_developer.model');

        if (!$apiKey || !$baseUrl || !$model) {
            $task->update([
                'status' => 'needs_configuration',
                'error_message' => 'AI provider is not configured. Set AI_DEVELOPER_API_KEY, AI_DEVELOPER_BASE_URL and AI_DEVELOPER_MODEL in the server environment.',
            ]);

            return redirect()->route('admin.ai-developer.index')->with('warning', 'AI connection is not configured yet. No external request was made.');
        }

        $systemPrompt = <<<'PROMPT'
You are the read-only diagnostic assistant for Alom's English Center LMS, a Laravel 12 / PHP 8.3 application.
Analyze the administrator's problem and provide: (1) likely cause, (2) safe investigation steps, (3) a proposed code-level fix with file paths only when justified, (4) tests that should be run, and (5) risks/rollback notes.
You do not have repository access in this stage. Never claim you inspected files, changed code, ran tests, committed changes, or deployed anything.
Never request passwords, API keys, payment credentials, personal student data, or production database dumps.
Do not recommend direct production edits, disabling security controls, bypassing branch protection, or running destructive SQL.
Clearly label assumptions and ask for sanitized error excerpts when needed. Reply in the same language as the administrator.
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(45)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $safeInstruction],
                    ],
                ]);

            if (!$response->successful()) {
                throw new \\RuntimeException('AI provider returned HTTP '.$response->status().'. Check provider settings and server logs.');
            }

            $answer = data_get($response->json(), 'choices.0.message.content');
            if (!is_string($answer) || trim($answer) === '') {
                throw new \\RuntimeException('AI provider returned an empty or unsupported response.');
            }

            $task->update(['response' => $answer, 'status' => 'completed', 'error_message' => null]);

            return redirect()->route('admin.ai-developer.index')->with('success', 'AI diagnosis completed. Review the advice before making changes.');
        } catch (Throwable $exception) {
            // Do not log provider request/response payloads or exception context that could contain secrets.
            $task->update([
                'status' => 'failed',
                'error_message' => 'The AI request failed. Check the provider configuration and application logs.',
            ]);

            return redirect()->route('admin.ai-developer.index')->with('error', 'AI analysis failed. No code or production data was changed.');
        }
    }

    private function redactSensitiveText(string $value): string
    {
        $value = preg_replace('/(Bearer\\s+)[A-Za-z0-9._\\-]+/i', '$1[REDACTED]', $value) ?? $value;

        return preg_replace(
            '/\\b(sk-[A-Za-z0-9_-]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,})\\b/',
            '[REDACTED_SECRET]',
            $value
        ) ?? $value;
    }
}
