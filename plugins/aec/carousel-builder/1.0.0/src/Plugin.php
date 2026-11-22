<?php

namespace Aloms\CarouselBuilder;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/Carousel.php';
require_once __DIR__.'/CarouselItem.php';
require_once __DIR__.'/CarouselBuilderController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('carousel-builder', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/carousels', 'admin.carousels.', function (): void {
            Route::get('/', [CarouselBuilderController::class, 'index'])->name('index');
            Route::get('/create', [CarouselBuilderController::class, 'create'])->name('create');
            Route::post('/', [CarouselBuilderController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::get('/{carousel}/edit', [CarouselBuilderController::class, 'edit'])->whereNumber('carousel')->name('edit');
            Route::put('/{carousel}', [CarouselBuilderController::class, 'update'])->whereNumber('carousel')->middleware('throttle:20,1')->name('update');
            Route::delete('/{carousel}', [CarouselBuilderController::class, 'destroy'])->whereNumber('carousel')->middleware('throttle:10,1')->name('destroy');
        }, 'carousels.manage');

        $context->registerPortalNavigation('admin', 'Carousel Builder', 'admin.carousels.index', [
            'icon' => 'gallery-horizontal',
            'permission' => 'carousels.manage',
        ]);
    }

    public function boot(PluginContext $context): void
    {
        View::composer('landing', function ($view): void {
            if (! \Illuminate\Support\Facades\Schema::hasTable('website_carousels')) {
                $view->with('websiteCarousels', collect());
                return;
            }

            $carousels = Carousel::query()->visible()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Carousel $carousel): array => [
                    'id' => $carousel->id,
                    'title' => $carousel->title,
                    'slug' => $carousel->slug,
                    'source_type' => $carousel->source_type,
                    'desktop_cards' => $carousel->desktop_cards,
                    'mobile_cards' => $carousel->mobile_cards,
                    'autoplay' => $carousel->autoplay,
                    'interval_ms' => $carousel->interval_ms,
                    'show_arrows' => $carousel->show_arrows,
                    'show_dots' => $carousel->show_dots,
                    'items' => $carousel->displayItems(),
                ])->filter(fn (array $carousel): bool => $carousel['items']->isNotEmpty())->values();

            $view->with('websiteCarousels', $carousels);
        });
    }
};