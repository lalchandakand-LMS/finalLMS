<?php

namespace Aloms\\UniversalAiAssistant;

use App\\Contracts\\Ai\\AiProvider;
use App\\Services\\AiDeveloperProviderEndpoint;
use Illuminate\\Support\\Facades\\Http;
use RuntimeException;

final class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(private readonly AiDeveloperProviderEndpoint $endpoint) {}

    public function key(): string
    {
        return 'openai-compatible';
    }

    public function capabilities(string $model): ?array
    {
        $model = strtolower(trim($model));
        if (preg_match('/^gpt-4o(?:-[a-z0-9.-]+)?$/', $model) === 1
            || preg_match('/^gpt-4(?:\.[0-9]+)?(?:-[a-z0-9.-]+)?$/', $model) === 1
            || preg_match('/^gpt-3\.5-turbo(?:-[a-z0-9.-]+)?$/', $model) === 1) {
            return ['temperature' => true, 'output_token_parameter' => 'max_tokens', 'max_output_tokens' => 16384];
        }

        if (preg_match('/^(?:o1|o3|o4)(?:-[a-z0-9.-]+)?$/', $model) === 1) {
            return ['temperature' => false, 'output_token_parameter' => 'max_completion_tokens', 'max_output_tokens' => 100000];
        }

        return null;
    }

    public function discoverModels(string $apiKey, string $baseUrl): array
    {
        $response = Http::withToken($apiKey)->acceptJson()->timeout(20)
            ->withOptions(array_merge(['allow_redirects' => false, 'stream' => true], $this->endpoint->curlOptionsFor($baseUrl)))
            ->get(rtrim($baseUrl, '/').'/models');

        $payload = $this->boundedJson($response);
        if (! $response->successful()) {
            throw new RuntimeException('The provider could not return its available models.');
        }

        return collect(is_array($payload['data'] ?? null) ? $payload['data'] : [])
            ->map(function ($item): ?array {
                $id = is_array($item) ? ($item['id'] ?? null) : null;
                if (! is_string($id) || $id === '' || strlen($id) > 120) return null;
                return ['id' => $id, 'capabilities' => $this->capabilities($id)];
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values()
            ->all();
    }

    public function complete(string $apiKey, string $baseUrl, string $model, array $messages, float $temperature, int $maxOutputTokens): array
    {
        $capabilities = $this->capabilities($model);
        if ($capabilities === null) {
            throw new RuntimeException('The selected model has no verified capability profile.');
        }
        if ($maxOutputTokens < 1 || $maxOutputTokens > $capabilities['max_output_tokens']) {
            throw new RuntimeException('The requested output limit exceeds the selected model capability.');
        }

        $payload = [
            'model' => $model,
            $capabilities['output_token_parameter'] => $maxOutputTokens,
            'messages' => $messages,
        ];
        if ($capabilities['temperature']) {
            if ($temperature < 0 || $temperature > 2) throw new RuntimeException('Temperature is outside the supported range.');
            $payload['temperature'] = $temperature;
        }

        $response = Http::withToken($apiKey)->acceptJson()->timeout(40)
            ->withOptions(array_merge(['allow_redirects' => false, 'stream' => true], $this->endpoint->curlOptionsFor($baseUrl)))
            ->post(rtrim($baseUrl, '/').'/chat/completions', $payload);

        $decoded = $this->boundedJson($response);
        if (! $response->successful()) throw new RuntimeException('The AI provider request failed.');

        $answer = data_get($decoded, 'choices.0.message.content');
        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException('The AI provider returned an unsupported response.');
        }

        $reported = (int) data_get($decoded, 'usage.total_tokens', 0);
        return ['content' => trim($answer), 'total_tokens' => $reported > 0 ? $reported : null];
    }

    public function stream(string $apiKey, string $baseUrl, string $model, array $messages, float $temperature, int $maxOutputTokens, callable $onDelta): array
    {
        $capabilities = $this->capabilities($model);
        if ($capabilities === null || $maxOutputTokens < 1 || $maxOutputTokens > $capabilities['max_output_tokens']) {
            throw new RuntimeException('The selected model has no verified capability profile or the output limit is invalid.');
        }

        $payload = [
            'model' => $model,
            $capabilities['output_token_parameter'] => $maxOutputTokens,
            'messages' => $messages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];
        if ($capabilities['temperature']) {
            if ($temperature < 0 || $temperature > 2) throw new RuntimeException('Temperature is outside the supported range.');
            $payload['temperature'] = $temperature;
        }

        $response = Http::withToken($apiKey)->accept('text/event-stream')->timeout(120)
            ->withOptions(array_merge(['allow_redirects' => false, 'stream' => true], $this->endpoint->curlOptionsFor($baseUrl)))
            ->post(rtrim($baseUrl, '/').'/chat/completions', $payload);
        if (! $response->successful()) throw new RuntimeException('The AI provider request failed.');

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $answer = '';
        $totalTokens = null;
        $maxBytes = 524288;
        while (! $body->eof()) {
            $buffer .= $body->read(4096);
            if (strlen($buffer) + strlen($answer) > $maxBytes) {
                throw new RuntimeException('The AI provider response exceeded the configured size limit.');
            }

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);
                if (! str_starts_with($line, 'data:')) continue;
                $data = trim(substr($line, 5));
                if ($data === '[DONE]' || $data === '') continue;
                $event = json_decode($data, true);
                if (! is_array($event)) throw new RuntimeException('The AI provider returned an invalid stream event.');

                $delta = data_get($event, 'choices.0.delta.content');
                if (is_string($delta) && $delta !== '') {
                    $answer .= $delta;
                    $onDelta($delta);
                }
                $reported = (int) data_get($event, 'usage.total_tokens', 0);
                if ($reported > 0) $totalTokens = $reported;
            }
        }

        if (trim($buffer) !== '' && str_starts_with(trim($buffer), 'data:')) {
            $data = trim(substr(trim($buffer), 5));
            if ($data !== '' && $data !== '[DONE]') {
                $event = json_decode($data, true);
                $delta = is_array($event) ? data_get($event, 'choices.0.delta.content') : null;
                if (is_string($delta) && $delta !== '') {
                    $answer .= $delta;
                    $onDelta($delta);
                }
                $reported = is_array($event) ? (int) data_get($event, 'usage.total_tokens', 0) : 0;
                if ($reported > 0) $totalTokens = $reported;
            }
        }

        if (trim($answer) === '') throw new RuntimeException('The AI provider returned an empty stream.');
        return ['content' => trim($answer), 'total_tokens' => $totalTokens];
    }

    private function boundedJson($response): array
    {
        $maxBytes = 524288;
        $length = $response->header('Content-Length');
        if (is_numeric($length) && (int) $length > $maxBytes) {
            throw new RuntimeException('The AI provider response exceeded the configured size limit.');
        }

        $body = $response->toPsrResponse()->getBody();
        $raw = '';
        while (! $body->eof()) {
            $raw .= $body->read(min(8192, $maxBytes + 1 - strlen($raw)));
            if (strlen($raw) > $maxBytes) throw new RuntimeException('The AI provider response exceeded the configured size limit.');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) throw new RuntimeException('The AI provider returned invalid JSON.');
        return $decoded;
    }
}
