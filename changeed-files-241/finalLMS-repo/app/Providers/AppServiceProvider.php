<?php

namespace App\Providers;

use App\Services\Payments\Gateways\BkashGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function ($app): PaymentGatewayManager {
            $manager = new PaymentGatewayManager($app);
            $manager->register('bkash', BkashGateway::class);

            return $manager;
        });
    }

    public function boot(): void
    {
        //
    }
}
