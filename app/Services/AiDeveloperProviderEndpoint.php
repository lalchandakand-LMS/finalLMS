<?php

namespace App\Services;

use Closure;
use RuntimeException;

class AiDeveloperProviderEndpoint
{
    /** @var Closure(string): array<int, array<string, mixed>> */
    private Closure $dnsResolver;

    public function __construct(?Closure $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver ?? static fn (string $host): array => dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    }

    /**
     * Resolve and pin the allow-listed HTTPS endpoint to validated public addresses.
     * The URL hostname remains unchanged, preserving TLS hostname verification and SNI.
     *
     * @return array{curl: array<int, array<int, string>>}
     */
    public function curlOptionsFor(string $url): array
    {
        if (!extension_loaded('curl') || !defined('CURLOPT_RESOLVE')) {
            throw new RuntimeException('Secure provider connection is unavailable.');
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!is_array($parts) || $host === '' || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('Secure provider connection is unavailable.');
        }

        $records = ($this->dnsResolver)($host);
        if ($records === []) {
            throw new RuntimeException('Secure provider connection is unavailable.');
        }

        $addresses = [];
        foreach ($records as $record) {
            $type = strtoupper((string) ($record['type'] ?? ''));
            if (!in_array($type, ['A', 'AAAA'], true)) {
                continue;
            }

            $address = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            $familyFlag = $type === 'A' ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
            if ($address === '' || filter_var($address, FILTER_VALIDATE_IP, $flags | $familyFlag) === false) {
                throw new RuntimeException('Secure provider connection is unavailable.');
            }

            $addresses[] = $address;
        }

        if ($addresses === []) {
            throw new RuntimeException('Secure provider connection is unavailable.');
        }

        $resolveEntries = array_map(
            static fn (string $address): string => $host.':443:'.(str_contains($address, ':') ? '['.$address.']' : $address),
            array_values(array_unique($addresses))
        );

        return ['curl' => [CURLOPT_RESOLVE => $resolveEntries]];
    }
}
