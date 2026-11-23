<?php

declare(strict_types=1);

namespace Aloms\UniversalPayment;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;

require_once __DIR__.'/BkashCheckoutService.php';
require_once __DIR__.'/BkashGateway.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        $context->registerPaymentGateway('bkash', Gateways\BkashGateway::class);
    }

    public function boot(PluginContext $context): void
    {
        // Gateway adapters are resolved lazily by PaymentGatewayManager.
    }
};
