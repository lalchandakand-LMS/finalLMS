<?php

namespace Aloms\FlashSale;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/AdminFlashSaleController.php';

return new class implements PluginContract
{
    public function register(PluginContext $context): void
    {
        View::addNamespace('flash-sale', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/flash-sales', 'admin.flash-sales.', function (): void {
            Route::get('/', [AdminFlashSaleController::class, 'index'])->name('index');
            Route::post('/', [AdminFlashSaleController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::put('/{flashSale}', [AdminFlashSaleController::class, 'update'])->whereNumber('flashSale')->middleware('throttle:20,1')->name('update');
            Route::delete('/{flashSale}', [AdminFlashSaleController::class, 'destroy'])->whereNumber('flashSale')->name('destroy');
        }, 'flash-sale.manage');

        $context->routesForPortal('public', 'flash-sale', 'flash-sales.', function (): void {
            Route::get('/', [AdminFlashSaleController::class, 'publicIndex'])->name('public');
        });

        $context->registerPortalNavigation('admin', 'Flash Sale', 'admin.flash-sales.index', [
            'icon' => '🔥',
            'permission' => 'flash-sale.manage',
        ]);
    }

    public function boot(PluginContext $context): void
    {
        // Shared LMS payment flows read campaign pricing only while this plugin is active.
    }
};
