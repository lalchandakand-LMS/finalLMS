<?php

namespace Tests\Unit;

use App\Services\Integrations\Connectors\TelegramBotConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotConnectorTest extends TestCase
{
    public function test_telegram_credentials_are_validated_without_sending_messages(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['id' => 123456, 'username' => 'aec_test_bot'],
        ], 200)]);

        $result = (new TelegramBotConnector())->validateCredentials([
            'bot_token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
            'webhook_secret' => 'aec_secret_123',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('123456', $result['account_id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getMe'));
    }

    public function test_telegram_webhooks_require_constant_time_secret_match_and_normalize_update_id(): void
    {
        $connector = new TelegramBotConnector();
        $request = Request::create('/', 'POST', [], [], [], [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'aec_secret_123',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'update_id' => 987654,
            'message' => ['message_id' => 44, 'chat' => ['id' => 7654321], 'text' => 'private message body'],
        ], JSON_THROW_ON_ERROR));

        $this->assertTrue($connector->verifyWebhook($request, ['webhook_secret' => 'aec_secret_123']));
        $event = $connector->normalizeWebhook($request);
        $this->assertSame('987654', $event['event_id']);
        $this->assertSame('7654321', $event['external_account_id']);
        $this->assertArrayNotHasKey('text', $event['payload']);
        $this->assertFalse($connector->verifyWebhook($request, ['webhook_secret' => 'wrong_secret']));
    }

    public function test_telegram_outbound_message_returns_provider_acknowledgement(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 55],
        ], 200)]);

        $result = (new TelegramBotConnector())->sendMessage([
            'bot_token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        ], ['chat_id' => '7654321', 'text' => 'Class reminder']);

        $this->assertTrue($result['accepted']);
        $this->assertSame('55', $result['provider_message_id']);
        $this->assertSame('accepted', $result['status']);
    }
}
