<?php

namespace App\Providers;

use App\Services\Payments\Gateways\BkashGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Contracts\Ai\AiProvider;
use App\Modules\Ai\OpenAiCompatibleProvider;
use App\Services\Integrations\ApiConnectorManager;
use App\Services\Integrations\Connectors\TelegramBotConnector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, OpenAiCompatibleProvider::class);
        $this->app->singleton(ApiConnectorManager::class, function (): ApiConnectorManager {
            $manager = new ApiConnectorManager();
            $manager->register('telegram', TelegramBotConnector::class);
            return $manager;
        });
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
