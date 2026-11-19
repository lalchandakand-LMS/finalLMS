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

    public function test_non_default_https_ports_are_rejected_before_dns_resolution(): void
    {
        $resolved = false;
        $endpoint = new AiDeveloperProviderEndpoint(static function (string $host) use (&$resolved): array {
            $resolved = true;
            return [['type' => 'A', 'ip' => '93.184.216.34']];
        });

        $this->expectException(RuntimeException::class);
        try {
            $endpoint->curlOptionsFor('https://ai.example.test:8443/v1');
        } finally {
            $this->assertFalse($resolved, 'DNS must not be resolved for a URL whose port is not pinned.');
        }
    }

    public function test_provider_urls_with_userinfo_query_or_fragment_are_rejected(): void
    {
        $endpoint = new AiDeveloperProviderEndpoint(static fn (string $host): array => [
            ['type' => 'A', 'ip' => '93.184.216.34'],
        ]);

        foreach ([
            'https://user:secret@ai.example.test/v1',
            'https://ai.example.test/v1?redirect=1',
            'https://ai.example.test/v1#fragment',
        ] as $url) {
            try {
                $endpoint->curlOptionsFor($url);
                $this->fail('Unsafe provider URL was accepted: '.$url);
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

}
