<?php

namespace App\Services\Integrations\Connectors;

use App\Contracts\Integrations\ApiConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TelegramBotConnector implements ApiConnector
{
    public function key(): string { return 'telegram'; }
    public function label(): string { return 'Telegram Bot API'; }

    public function credentialSchema(): array
    {
        return [
            'bot_token' => ['label' => 'Bot token', 'required' => true, 'secret' => true],
            'webhook_secret' => ['label' => 'Webhook secret token', 'required' => true, 'secret' => true],
        ];
    }

    public function capabilities(): array
    {
        return ['inbound_text' => true, 'outbound_text' => true, 'media' => false, 'templates' => false, 'delivery_receipts' => false];
    }

    public function validateCredentials(array $credentials): array
    {
        $token = trim((string) ($credentials['bot_token'] ?? ''));
        $webhookSecret = (string) ($credentials['webhook_secret'] ?? '');
        if ($token === '' || strlen($token) > 256
            || preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $webhookSecret) !== 1) {
            return ['valid' => false];
        }
        $response = Http::timeout(10)->acceptJson()->get('https://api.telegram.org/bot'.$token.'/getMe');
        if (! $response->successful() || $response->json('ok') !== true) {
            return ['valid' => false];
        }
        $bot = $response->json('result', []);
        return ['valid' => true, 'account_id' => (string) ($bot['id'] ?? ''), 'scopes' => ['sendMessage', 'webhook_updates'], 'bot_username' => $bot['username'] ?? null];
    }

    public function verifyWebhook(Request $request, array $credentials): bool
    {
        $secret = (string) ($credentials['webhook_secret'] ?? '');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        return $secret !== '' && $provided !== '' && hash_equals($secret, $provided);
    }

    public function normalizeWebhook(Request $request): array
    {
        $payload = $request->json()->all();
        $updateId = $payload['update_id'] ?? null;
        if (! is_int($updateId) && ! ctype_digit((string) $updateId)) {
            throw new RuntimeException('Telegram webhook update ID is invalid.');
        }
        $message = $payload['message'] ?? $payload['edited_message'] ?? [];
        return [
            'event_id' => (string) $updateId,
            'event_type' => isset($payload['message']) ? 'message' : (isset($payload['edited_message']) ? 'edited_message' : 'update'),
            'external_account_id' => isset($message['chat']['id']) ? (string) $message['chat']['id'] : null,
            'payload' => ['message_id' => $message['message_id'] ?? null, 'chat_id' => $message['chat']['id'] ?? null, 'has_text' => isset($message['text'])],
        ];
    }

    public function sendMessage(array $credentials, array $message): array
    {
        $chatId = trim((string) ($message['chat_id'] ?? ''));
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === '' || $text === '' || mb_strlen($text) > 4096) {
            throw new RuntimeException('Telegram message requires a valid chat ID and text up to 4096 characters.');
        }
        $token = (string) ($credentials['bot_token'] ?? '');
        $response = Http::timeout(15)->acceptJson()->post('https://api.telegram.org/bot'.$token.'/sendMessage', ['chat_id' => $chatId, 'text' => $text]);
        if (! $response->successful() || $response->json('ok') !== true) {
            return ['accepted' => false, 'provider_message_id' => null, 'status' => 'failed'];
        }
        return ['accepted' => true, 'provider_message_id' => (string) $response->json('result.message_id'), 'status' => 'accepted'];
    }
}
