<?php

namespace Aloms\UniversalAiAssistant;

use App\Services\AiDeveloperProviderEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

final class AssistantController
{
    public function index(Request $request): View
    {
        $role = AssistantSettings::roleForUser((string) $request->user()->role);
        abort_unless($role !== null && $this->isAvailable($role), 404);

        $routeName = match ($role) {
            'admin' => 'admin.ai-assistant.messages.store',
            'branch_manager' => 'branch.ai-assistant.messages.store',
            'teacher' => 'teacher.ai-assistant.messages.store',
            'student' => 'student.ai-assistant.messages.store',
            'guardian' => 'guardian.ai-assistant.messages.store',
            default => 'support.ai-assistant.messages.store',
        };

        return view('universal-ai-assistant::chat', [
            'role' => $role,
            'messageRoute' => $routeName,
            'conversations' => DB::table('ai_assistant_conversations')
                ->where('user_id', $request->user()->id)
                ->where('assistant_role', $role)
                ->latest('updated_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $role = AssistantSettings::roleForUser((string) $request->user()->role);
        abort_unless($role !== null && $this->isAvailable($role), 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $settings = AssistantSettings::record();
        $todayRequests = DB::table('ai_assistant_messages')
            ->join('ai_assistant_conversations', 'ai_assistant_conversations.id', '=', 'ai_assistant_messages.conversation_id')
            ->where('ai_assistant_conversations.user_id', $request->user()->id)
            ->whereDate('ai_assistant_messages.created_at', today())
            ->where('ai_assistant_messages.message_role', 'user')
            ->count();
        if ($todayRequests >= $settings['daily_request_limit']) {
            return response()->json(['message' => __('Your daily AI request limit has been reached.')], 429);
        }

        $usedTokens = (int) DB::table('ai_assistant_messages')->where('message_role', 'assistant')
            ->whereDate('created_at', today())->sum('token_count');
        $remainingDailyTokens = AssistantTokenBudget::remaining((int) $settings['daily_token_budget'], $usedTokens);
        if ($remainingDailyTokens < 1) {
            return response()->json(['message' => __('The institution daily AI token budget has been reached.')], 429);
        }

        if (! empty($validated['conversation_id'])) {
            $conversation = DB::table('ai_assistant_conversations')
                ->where('id', $validated['conversation_id'])
                ->where('user_id', $request->user()->id)
                ->where('assistant_role', $role)
                ->first();
            abort_unless($conversation, 404);
        } else {
            $conversationId = DB::table('ai_assistant_conversations')->insertGetId([
                'user_id' => $request->user()->id,
                'assistant_role' => $role,
                'title' => mb_substr(trim($validated['message']), 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $conversation = DB::table('ai_assistant_conversations')->where('id', $conversationId)->first();
        }

        $message = $this->redactSensitiveText(trim($validated['message']));
        DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversation->id,
            'message_role' => 'user',
            'content' => $message,
            'token_count' => null,
            'model' => null,
            'created_at' => now(),
        ]);

        try {
            $apiKey = AssistantSettings::apiKey();
            if (! $apiKey) return response()->json(['message' => __('The AI provider key has not been configured.')], 503);

            $baseUrl = rtrim((string) $settings['base_url'], '/');
            $curlOptions = app(AiDeveloperProviderEndpoint::class)->curlOptionsFor($baseUrl);
            $history = DB::table('ai_assistant_messages')->where('conversation_id', $conversation->id)
                ->latest('id')->limit(12)->get()->reverse()->values();

            $messages = [['role' => 'system', 'content' => AssistantSettings::rolePrompt($role)]];
            foreach ($history as $item) $messages[] = ['role' => $item->message_role, 'content' => $item->content];

            // Reserve a conservative prompt-token estimate before allowing completion tokens.
            // This avoids sending an oversized completion allowance when little budget remains.
            $estimatedPromptTokens = AssistantTokenBudget::estimatePromptTokens($messages);
            $responseTokenLimit = AssistantTokenBudget::completionLimit(
                (int) $settings['max_tokens'],
                $remainingDailyTokens,
                $estimatedPromptTokens
            );
            if ($responseTokenLimit < 1) {
                return response()->json(['message' => __('The remaining daily AI token budget is insufficient for this prompt.')], 429);
            }
            $response = Http::withToken($apiKey)->acceptJson()->timeout(40)->withOptions(array_merge([
                'allow_redirects' => false,
                'stream' => true,
            ], $curlOptions))->post($baseUrl.'/chat/completions', [
                'model' => $settings['model'],
                'temperature' => $settings['temperature'],
                'max_tokens' => $responseTokenLimit,
                'messages' => $messages,
            ]);

            if (! $response->successful()) return response()->json(['message' => __('The AI provider is temporarily unavailable.')], 502);

            $maxBytes = 524288;
            $body = $response->toPsrResponse()->getBody();
            $contentLength = $response->header('Content-Length');
            if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) return response()->json(['message' => __('The AI provider response exceeded the configured size limit.')], 502);

            $raw = '';
            while (! $body->eof()) {
                $raw .= $body->read(min(8192, $maxBytes + 1 - strlen($raw)));
                if (strlen($raw) > $maxBytes) return response()->json(['message' => __('The AI provider response exceeded the configured size limit.')], 502);
            }

            $decoded = json_decode($raw, true);
            $answer = data_get($decoded, 'choices.0.message.content');
            if (! is_string($answer) || trim($answer) === '') return response()->json(['message' => __('The AI provider returned an unsupported response.')], 502);

            $answer = $this->redactSensitiveText(trim($answer));
            $tokens = max(0, (int) data_get($decoded, 'usage.total_tokens', 0));
            DB::table('ai_assistant_messages')->insert([
                'conversation_id' => $conversation->id,
                'message_role' => 'assistant',
                'content' => $answer,
                'token_count' => $tokens,
                'model' => (string) $settings['model'],
                'created_at' => now(),
            ]);
            DB::table('ai_assistant_conversations')->where('id', $conversation->id)->update(['updated_at' => now()]);

            return response()->json(['conversation_id' => $conversation->id, 'response' => $answer]);
        } catch (Throwable) {
            return response()->json(['message' => __('The AI request failed. Check provider settings and try again.')], 503);
        }
    }

    private function isAvailable(string $role): bool
    {
        $settings = AssistantSettings::record();

        return $settings['global_enabled']
            && ($settings['role_enabled'][$role] ?? false)
            && filled($settings['api_key_encrypted'])
            && $settings['data_access_mode'] === 'general_only';
    }

    private function redactSensitiveText(string $value): string
    {
        $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._-]+/i', '$1[REDACTED]', $value) ?? $value;
        return preg_replace('/\b(sk-[A-Za-z0-9_-]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,}|AKIA[0-9A-Z]{16})\b/', '[REDACTED_SECRET]', $value) ?? $value;
    }
}
