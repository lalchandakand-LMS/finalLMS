<?php

namespace Aloms\UniversalAiAssistant;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AssistantService
{
    private const ROLE_LABELS = [
        'admin' => 'Administrator',
        'branch_manager' => 'Branch Manager',
        'teacher' => 'Teacher',
        'student' => 'Student',
        'guardian' => 'Guardian',
        'accountant' => 'Support Staff',
    ];

    public function pruneExpiredConversations(): void
    {
        $settings = DB::table('ai_assistant_settings')->where('id', 1)->first();
        if (! $settings) {
            return;
        }

        $retentionDays = max(1, (int) $settings->retention_days);
        DB::table('ai_assistant_conversations')
            ->where('updated_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    public function reply(User $user, string $message, ?int $conversationId = null): array
    {
        $this->pruneExpiredConversations();
        $settings = DB::table('ai_assistant_settings')->where('id', 1)->first();
        if (! $settings || ! $settings->enabled) {
            throw new RuntimeException('The Universal AI Assistant is currently disabled by the administrator.');
        }

        $role = $user->role === 'accountant' ? 'support_staff' : $user->role;
        if (! isset(self::ROLE_LABELS[$user->role])) {
            throw new RuntimeException('This account role is not enabled for the AI Assistant.');
        }

        $roleSettings = $this->decode($settings->role_settings);
        if (($roleSettings[$role] ?? false) !== true) {
            throw new RuntimeException('The AI Assistant is disabled for your account role.');
        }

        if (! is_string($settings->api_key_encrypted) || $settings->api_key_encrypted === '' || ! is_string($settings->model) || trim($settings->model) === '') {
            throw new RuntimeException('The administrator has not configured the AI provider credentials and model.');
        }

        $apiKey = Crypt::decryptString($settings->api_key_encrypted);
        if ($apiKey === '') {
            throw new RuntimeException('The AI provider credentials are unavailable.');
        }

        $conversation = null;
        if ($conversationId !== null) {
            $conversation = DB::table('ai_assistant_conversations')
                ->where('id', $conversationId)->where('user_id', $user->id)->where('role', $role)->first();
            if (! $conversation) {
                throw new RuntimeException('Conversation not found for this account.');
            }
        } else {
            $conversationId = DB::table('ai_assistant_conversations')->insertGetId([
                'user_id' => $user->id,
                'role' => $role,
                'title' => mb_substr($message, 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $message,
            'created_at' => now(),
        ]);

        $history = DB::table('ai_assistant_messages')->where('conversation_id', $conversationId)
            ->orderByDesc('id')->limit(12)->get()->reverse()->values();

        $messages = [[
            'role' => 'system',
            'content' => 'You are the Alom’s English Center Universal AI Assistant for a '.$this->roleLabel($role).'. Provide general educational and operational guidance only. You have no access to private LMS records, grades, attendance, payments, student profiles, guardian links, staff records or branch records. Never claim that you checked or know private LMS data. Do not request passwords, OTPs, API keys or payment credentials. Answer in the language used by the user when possible.',
        ]];
        foreach ($history as $item) {
            $messages[] = ['role' => $item->role, 'content' => $item->content];
        }

        $budget = $settings->monthly_token_budget;
        $responseTokenLimit = (int) $settings->max_tokens;
        if ($budget !== null) {
            $used = (int) DB::table('ai_assistant_usage')->where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfMonth())->sum('total_tokens');
            $estimatedPromptTokens = 0;
            foreach ($messages as $promptMessage) {
                $estimatedPromptTokens += (int) ceil(mb_strlen((string) $promptMessage['content']) / 3);
            }
            $available = (int) $budget - $used - $estimatedPromptTokens;
            if ($available < 1) {
                throw new RuntimeException('The monthly AI token budget for this account has been reached.');
            }
            $responseTokenLimit = min($responseTokenLimit, $available);
        }

        $response = Http::withToken($apiKey)->acceptJson()->timeout(35)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $settings->model,
            'messages' => $messages,
            'temperature' => (float) $settings->temperature,
            'max_completion_tokens' => $responseTokenLimit,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('The AI provider request failed. Check the configured model, API key and provider status.');
        }

        $payload = $response->json();
        $answer = data_get($payload, 'choices.0.message.content');
        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException('The AI provider returned an empty response.');
        }

        $usage = $payload['usage'] ?? [];
        $inputTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $outputTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
        DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $answer,
            'created_at' => now(),
        ]);
        DB::table('ai_assistant_usage')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'provider' => 'openai',
            'model' => $settings->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'created_at' => now(),
        ]);
        DB::table('ai_assistant_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);

        return [
            'conversation_id' => $conversationId,
            'reply' => $answer,
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ];
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'branch_manager' => 'Branch Manager',
            'support_staff' => 'Support Staff',
            default => self::ROLE_LABELS[$role] ?? 'LMS User',
        };
    }
}