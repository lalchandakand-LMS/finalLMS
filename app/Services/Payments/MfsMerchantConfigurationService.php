<?php

namespace App\Services\Payments;

use App\Models\MfsMerchantConfiguration;
use InvalidArgumentException;

final class MfsMerchantConfigurationService
{
    private const PROVIDERS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'sslcommerz' => 'SSLCOMMERZ'];

    public function providers(): array
    {
        return self::PROVIDERS;
    }

    public function assertProvider(string $provider): void
    {
        if (! array_key_exists($provider, self::PROVIDERS)) {
            throw new InvalidArgumentException('Unsupported Bangladesh MFS provider.');
        }
    }

    public function configuration(string $provider): ?MfsMerchantConfiguration
    {
        $this->assertProvider($provider);
        return MfsMerchantConfiguration::query()->where('provider', $provider)->first();
    }

    public function hasStoredConfiguration(string $provider): bool
    {
        return $this->configuration($provider) !== null;
    }

    public function credentials(string $provider): array
    {
        $this->assertProvider($provider);
        $fallback = [];
        if ($provider === 'bkash') {
            foreach (['username', 'password', 'app_key', 'app_secret'] as $key) {
                $value = config('services.bkash.'.$key);
                if (is_string($value) && $value !== '') {
                    $fallback[$key] = $value;
                }
            }
        }
        $stored = $this->configuration($provider)?->credentials ?? [];
        return array_merge($fallback, is_array($stored) ? $stored : []);
    }

    public function credential(string $provider, string $key): ?string
    {
        $value = $this->credentials($provider)[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isBkashConfigured(): bool
    {
        if (! $this->hasStoredConfiguration('bkash') && ! (bool) config('services.bkash.enabled')) {
            return false;
        }
        $credentials = $this->credentials('bkash');
        foreach (['username', 'password', 'app_key', 'app_secret'] as $key) {
            if (! isset($credentials[$key]) || $credentials[$key] === '') {
                return false;
            }
        }

        $baseUrl = (string) config('services.bkash.base_url');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);

        // This configuration foundation is deliberately sandbox-only. Do not
        // let a sandbox credential record activate a production API endpoint.
        return $scheme === 'https'
            && $host === 'tokenized.sandbox.bka.sh'
            && ($port === null || $port === 443)
            && (bool) config('services.bkash.verify_ssl', true);
    }

    public function save(string $provider, array $credentials, int $userId, string $mode = 'sandbox'): MfsMerchantConfiguration
    {
        $this->assertProvider($provider);
        if (! in_array($mode, $provider === 'sslcommerz' ? ['sandbox', 'live'] : ['sandbox'], true)) {
            throw new InvalidArgumentException('The selected merchant mode is not supported for this provider.');
        }
        return MfsMerchantConfiguration::query()->updateOrCreate(
            ['provider' => $provider],
            ['mode' => $mode, 'credentials' => $credentials, 'updated_by' => $userId]
        );
    }
}
