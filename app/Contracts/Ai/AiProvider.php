<?php

namespace App\\Contracts\\Ai;

interface AiProvider
{
    public function key(): string;

    /** @return array{temperature: bool, output_token_parameter: string, max_output_tokens: int}|null */
    public function capabilities(string $model): ?array;

    /** @return array<int, array{id: string, capabilities: array}> */
    public function discoverModels(string $apiKey, string $baseUrl): array;

    /** @return array{content: string, total_tokens: int|null} */
    public function complete(string $apiKey, string $baseUrl, string $model, array $messages, float $temperature, int $maxOutputTokens): array;
}
