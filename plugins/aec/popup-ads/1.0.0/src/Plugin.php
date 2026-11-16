<?php

namespace Aloms\PopupAds;

use App\Contracts\Plugins\PluginContract;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/PopupContentSanitizer.php';
require_once __DIR__.'/PopupAdEvent.php';
require_once __DIR__.'/PopupCampaign.php';
require_once __DIR__.'/AdminPopupController.php';
require_once __DIR__.'/PublicPopupController.php';

return new class implements PluginContract
{
    public function register(PluginContext $context): void
    {
        View::addNamespace('popup-ads', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/visitor-popups', 'admin.visitor-popups.', function (): void {
            Route::get('/', [AdminPopupController::class, 'index'])->name('index');
            Route::get('/create', [AdminPopupController::class, 'create'])->name('create');
            Route::post('/', [AdminPopupController::class, 'store'])->name('store');
            Route::get('/export', [AdminPopupController::class, 'export'])->name('export');
            Route::post('/purge', [AdminPopupController::class, 'purge'])->name('purge');
            Route::get('/{visitorPopup}/edit', [AdminPopupController::class, 'edit'])->whereNumber('visitorPopup')->name('edit');
            Route::get('/{visitorPopup}/preview', [AdminPopupController::class, 'preview'])->whereNumber('visitorPopup')->name('preview');
            Route::put('/{visitorPopup}', [AdminPopupController::class, 'update'])->whereNumber('visitorPopup')->name('update');
            Route::patch('/{visitorPopup}/toggle', [AdminPopupController::class, 'toggle'])->whereNumber('visitorPopup')->name('toggle');
            Route::post('/{visitorPopup}/duplicate', [AdminPopupController::class, 'duplicate'])->whereNumber('visitorPopup')->name('duplicate');
            Route::patch('/{visitorPopup}/pause', [AdminPopupController::class, 'pause'])->whereNumber('visitorPopup')->name('pause');
            Route::patch('/{visitorPopup}/resume', [AdminPopupController::class, 'resume'])->whereNumber('visitorPopup')->name('resume');
            Route::patch('/{visitorPopup}/archive', [AdminPopupController::class, 'archive'])->whereNumber('visitorPopup')->name('archive');
            Route::delete('/{visitorPopup}', [AdminPopupController::class, 'destroy'])->whereNumber('visitorPopup')->name('destroy');
        }, 'popup-ads.manage');

        $context->routesForPortal('public', 'popups', 'popups.', function (): void {
            Route::get('/active', [PublicPopupController::class, 'active'])->middleware('throttle:60,1')->name('active');
            Route::post('/{popup}/events', [PublicPopupController::class, 'track'])->whereNumber('popup')->middleware('throttle:30,1')->name('events');
        });

        $context->bind('popup_ads.render', fn () => function (Request $request): string {
            if (! Schema::hasTable('plugin_registry') || ! Schema::hasTable('visitor_popups')) {
                return '';
            }

            $active = PluginInstallation::query()
                ->where('plugin_key', 'aec.popup-ads')
                ->where('status', 'active')
                ->exists();
            if (! $active) {
                return '';
            }

            $candidates = PopupCampaign::query()
                ->currentlyEligible()
                ->with(['image', 'mobileImage', 'events'])
                ->orderByDesc('priority')
                ->latest('id')
                ->limit(25)
                ->get();

            $matched = $candidates->first(fn (PopupCampaign $campaign): bool =>
                $campaign->matchesRequestContext($request)
            );

            if (! $matched) {
                return '';
            }

            $consentGranted = $request->cookie('marketing_consent') === 'accepted';

            return view('popup-ads::visitor-popup', [
                'payload' => $matched->toPublicPayload($request->user(), $consentGranted),
                'previewMode' => false,
            ])->render();
        });
    }

    public function boot(PluginContext $context): void
    {
        // Runtime routes and view namespace are registered on active verified plugins only.
    }
};
