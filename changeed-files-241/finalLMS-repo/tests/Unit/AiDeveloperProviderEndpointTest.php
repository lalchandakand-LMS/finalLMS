<?php

namespace Tests\Unit;

use App\Services\AiDeveloperProviderEndpoint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AiDeveloperProviderEndpointTest extends TestCase
{
    public function test_public_ipv4_and_ipv6_answers_are_pinned(): void
    {
        $endpoint = new AiDeveloperProviderEndpoint(static fn (string $host): array => [
            ['type' => 'A', 'ip' => '93.184.216.34'],
            ['type' => 'AAAA', 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946'],
        ]);

        $options = $endpoint->curlOptionsFor('https://ai.example.test/v1');

        $this->assertSame([
            'ai.example.test:443:93.184.216.34',
            'ai.example.test:443:[2606:2800:220:1:248:1893:25c8:1946]',
        ], $options['curl'][CURLOPT_RESOLVE]);
    }

    public function test_mixed_public_and_private_dns_answers_are_rejected(): void
    {
        $endpoint = new AiDeveloperProviderEndpoint(static fn (string $host): array => [
            ['type' => 'A', 'ip' => '93.184.216.34'],
            ['type' => 'A', 'ip' => '10.0.0.8'],
        ]);

        $this->expectException(RuntimeException::class);
        $endpoint->curlOptionsFor('https://ai.example.test/v1');
    }

    public function test_private_ipv6_dns_answers_are_rejected(): void
    {
        $endpoint = new AiDeveloperProviderEndpoint(static fn (string $host): array => [
            ['type' => 'AAAA', 'ipv6' => 'fd00::1'],
        ]);

        $this->expectException(RuntimeException::class);
        $endpoint->curlOptionsFor('https://ai.example.test/v1');
    }

    public function test_empty_dns_answers_fail_closed(): void
    {
        $endpoint = new AiDeveloperProviderEndpoint(static fn (string $host): array => []);

        $this->expectException(RuntimeException::class);
        $endpoint->curlOptionsFor('https://ai.example.test/v1');
    }
}
