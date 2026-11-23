<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Setting;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private array $drivers = [];

    /** @var array<string, string> Gateway key to owning plugin key. */
    private array $pluginOwners = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param class-string<PaymentGateway> $driver */
    public function register(string $key, string $driver): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key) || ! is_subclass_of($driver, PaymentGateway::class)) {
            throw new InvalidArgumentException('Invalid payment gateway registration.');
        }

        if (isset($this->drivers[$key])) {
            throw new InvalidArgumentException('A payment gateway with this key is already registered.');
        }

        $this->drivers[$key] = $driver;
    }

    /** @param class-string<PaymentGateway> $driver */
    public function registerPluginGateway(string $pluginKey, string $key, string $driver): void
    {
        if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/D', $pluginKey) !== 1
            || preg_match('/^[a-z][a-z0-9_]{1,39}$/D', $key) !== 1
            || ! is_subclass_of($driver, PaymentGateway::class)) {
            throw new InvalidArgumentException('Invalid payment gateway plugin registration.');
        }

        if (isset($this->drivers[$key])) {
            throw new InvalidArgumentException('A payment gateway with this key is already registered.');
        }

        $this->drivers[$key] = $driver;
        $this->pluginOwners[$key] = $pluginKey;
    }

    public function driver(string $key): PaymentGateway
    {
        $class = $this->drivers[$key] ?? null;
        if (! $class) {
            throw new InvalidArgumentException('The requested payment gateway is not installed.');
        }

        return $this->container->make($class);
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        $rawRegistry = Setting::value('payment_gateway_registry');
        if ($rawRegistry === null) {
            $legacyMethods = Setting::paymentMethods();
            $rawRegistry = json_encode(['bkash' => (bool) ($legacyMethods['bkash']['enabled'] ?? false) || (bool) config('services.bkash.enabled')]);
        }
        $saved = json_decode((string) $rawRegistry, true);
        $saved = is_array($saved) ? $saved : [];
        $catalog = [];

        foreach ($this->drivers as $key => $class) {
            $gateway = $this->container->make($class);
            $configured = $gateway->isConfigured();
            $enabled = (bool) ($saved[$key] ?? false);
            $catalog[$key] = [
                'key' => $gateway->key(),
                'plugin_key' => $this->pluginOwners[$key] ?? null,
                'name' => $gateway->label(),
                'currencies' => $gateway->supportedCurrencies(),
                'installed' => true,
                'configured' => $configured,
                'enabled' => $enabled,
                'available' => $configured && $enabled,
                'status' => ! $configured ? 'needs_configuration' : ($enabled ? 'enabled' : 'disabled'),
            ];
        }

        return $catalog;
    }

    public function isAvailable(string $key): bool
    {
        return (bool) ($this->catalog()[$key]['available'] ?? false);
    }

    /** @return list<string> */
    public function installedKeys(): array
    {
        return array_keys($this->drivers);
    }
}
