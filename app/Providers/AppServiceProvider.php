<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment adapters are registered only by active, signature-verified
        // gateway plugins through PluginContext::registerPaymentGateway().
        $this->app->singleton(PaymentGatewayManager::class, fn ($app): PaymentGatewayManager => new PaymentGatewayManager($app));
    }

    public function boot(): void
    {
        //
    }
}
