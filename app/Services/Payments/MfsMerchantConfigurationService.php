<?php

namespace App\Services\Payments;

use App\Models\MfsMerchantConfiguration;
use InvalidArgumentException;

final class MfsMerchantConfigurationService
{
    private const PROVIDERS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket'];

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
        return filled(config('services.bkash.base_url'))
            && parse_url((string) config('services.bkash.base_url'), PHP_URL_SCHEME) === 'https'
            && (bool) config('services.bkash.verify_ssl', true);
    }

    public function save(string $provider, array $credentials, int $userId): MfsMerchantConfiguration
    {
        $this->assertProvider($provider);
        return MfsMerchantConfiguration::query()->updateOrCreate(
            ['provider' => $provider],
            ['mode' => 'sandbox', 'credentials' => $credentials, 'updated_by' => $userId]
        );
    }
}
