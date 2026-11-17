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
        AssistantSettings::pruneExpiredConversations();

        $routeName = match ($role) {
            'admin' => 'admin.ai-assistant.messages.store',
            'branch_manager' => 'branch.ai-assistant.messages.store',
            'teacher' => 'teacher.ai-assistant.messages.store',
            'student' => 'student.ai-assistant.messages.store',
            'guardian' => 'guardian.ai-assistant.messages.store',
            default => 'support.ai-assistant.messages.store',
        };
        $indexRoute = match ($role) {
            'admin' => 'admin.ai-assistant.index',
            'branch_manager' => 'branch.ai-assistant.index',
            'teacher' => 'teacher.ai-assistant.index',
            'student' => 'student.ai-assistant.index',
            'guardian' => 'guardian.ai-assistant.index',
            default => 'support.ai-assistant.index',
        };

        $conversationId = $request->integer('conversation_id');
        $activeConversation = null;
        $messages = collect();
        if ($conversationId > 0) {
            $activeConversation = DB::table('ai_assistant_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $request->user()->id)
                ->where('assistant_role', $role)
                ->first();
            abort_unless($activeConversation, 404);

            $messages = DB::table('ai_assistant_messages')
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('id')
                ->get();
        }

        return view('universal-ai-assistant::chat', [
            'role' => $role,
            'messageRoute' => $routeName,
            'indexRoute' => $indexRoute,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
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
        AssistantSettings::pruneExpiredConversations();

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $message = $this->redactSensitiveText(trim($validated['message']));
        $settings = AssistantSettings::record();
        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = DB::table('ai_assistant_conversations')
                ->where('id', $validated['conversation_id'])
                ->where('user_id', $request->user()->id)
                ->where('assistant_role', $role)
                ->first();
            abort_unless($conversation, 404);
        }

        if (! AssistantRequestLimit::reserve((int) $request->user()->id, (int) $settings['daily_request_limit'])) {
            return response()->json(['message' => __('Your daily AI request limit has been reached.')], 429);
        }

        if (! $conversation) {
            $conversationId = DB::table('ai_assistant_conversations')->insertGetId([
                'user_id' => $request->user()->id,
                'assistant_role' => $role,
                'title' => mb_substr($message, 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $conversation = DB::table('ai_assistant_conversations')->where('id', $conversationId)->first();
        }

        DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversation->id,
            'message_role' => 'user',
            'content' => $message,
            'token_count' => null,
            'model' => null,
            'created_at' => now(),
        ]);

        $reservationId = null;
        $reservationSettled = false;
        try {
            $apiKey = AssistantSettings::apiKey();
            if (! $apiKey) return response()->json(['message' => __('The AI provider key has not been configured.')], 503);

            $baseUrl = rtrim((string) $settings['base_url'], '/');
            $curlOptions = app(AiDeveloperProviderEndpoint::class)->curlOptionsFor($baseUrl);
            $history = DB::table('ai_assistant_messages')->where('conversation_id', $conversation->id)
                ->latest('id')->limit(12)->get()->reverse()->values();

            $messages = [['role' => 'system', 'content' => AssistantSettings::rolePrompt($role)]];
            foreach ($history as $item) $messages[] = ['role' => $item->message_role, 'content' => $item->content];

            $reservation = AssistantTokenBudget::reserve(
                (int) $settings['daily_token_budget'],
                (int) $settings['max_tokens'],
                $messages
            );
            if ($reservation === null) {
                return response()->json(['message' => __('The institution daily AI token budget is exhausted or insufficient for this prompt.')], 429);
            }
            $reservationId = $reservation['id'];

            $response = Http::withToken($apiKey)->acceptJson()->timeout(40)->withOptions(array_merge([
                'allow_redirects' => false,
                'stream' => true,
            ], $curlOptions))->post($baseUrl.'/chat/completions', [
                'model' => $settings['model'],
                'temperature' => $settings['temperature'],
                'max_tokens' => $reservation['max_tokens'],
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
            $accountedTokens = $tokens > 0 ? $tokens : $reservation['reserved_tokens'];
            AssistantTokenBudget::settle($reservationId, $accountedTokens);
            $reservationSettled = true;
            DB::table('ai_assistant_messages')->insert([
                'conversation_id' => $conversation->id,
                'message_role' => 'assistant',
                'content' => $answer,
                'token_count' => $accountedTokens,
                'model' => (string) $settings['model'],
                'created_at' => now(),
            ]);
            DB::table('ai_assistant_conversations')->where('id', $conversation->id)->update(['updated_at' => now()]);

            return response()->json(['conversation_id' => $conversation->id, 'response' => $answer]);
        } catch (Throwable) {
            return response()->json(['message' => __('The AI request failed. Check provider settings and try again.')], 503);
        } finally {
            if ($reservationId !== null && ! $reservationSettled) {
                AssistantTokenBudget::release($reservationId);
            }
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
