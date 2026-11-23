<?php

declare(strict_types=1);

namespace Aloms\AdmissionManagement;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/AdmissionApplication.php';
require_once __DIR__.'/AdmissionDocument.php';
require_once __DIR__.'/AdmissionApplicationEvent.php';
require_once __DIR__.'/AdmissionController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('admission-management', dirname(__DIR__).'/resources/views');
        $context->routesForPortal('public', 'admission', 'admission.public.', function (): void {
            Route::get('/apply', [AdmissionController::class, 'publicForm'])->name('apply');
            Route::post('/apply', [AdmissionController::class, 'applyPublic'])->middleware('throttle:3,10')->name('apply.store');
            Route::get('/track', [AdmissionController::class, 'trackForm'])->name('track');
            Route::post('/track', [AdmissionController::class, 'track'])->middleware('throttle:5,10')->name('track.submit');
        });
        $context->routesForPortal('admin', 'admin/admissions', 'admin.admissions.', function (): void {
            Route::get('/', [AdmissionController::class, 'index'])->name('index');
            Route::get('/reports', [AdmissionController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [AdmissionController::class, 'exportReport'])->middleware('throttle:10,1')->name('reports.export');
            Route::post('/', [AdmissionController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::post('/{application}/review', [AdmissionController::class, 'review'])->whereNumber('application')->middleware('throttle:20,1')->name('review');
            Route::post('/{application}/documents', [AdmissionController::class, 'storeDocument'])->whereNumber('application')->middleware('throttle:10,1')->name('documents.store');
            Route::post('/{application}/documents/{document}/review', [AdmissionController::class, 'reviewDocument'])->whereNumber('application')->whereNumber('document')->middleware('throttle:20,1')->name('documents.review');
            Route::get('/{application}/documents/{document}', [AdmissionController::class, 'downloadDocument'])->whereNumber('application')->name('documents.download');
            Route::post('/{application}/convert-to-student', [AdmissionController::class, 'convertToStudent'])->whereNumber('application')->middleware('throttle:5,1')->name('convert');
        }, 'admissions.manage');
        $context->routesForPortal('branch', 'branch/admissions', 'branch.admissions.', function (): void {
            Route::get('/', [AdmissionController::class, 'index'])->name('index');
            Route::post('/', [AdmissionController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::get('/reports', [AdmissionController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [AdmissionController::class, 'exportReport'])->middleware('throttle:10,1')->name('reports.export');
            Route::post('/{application}/review', [AdmissionController::class, 'review'])->whereNumber('application')->middleware('throttle:20,1')->name('review');
            Route::post('/{application}/documents', [AdmissionController::class, 'storeDocument'])->whereNumber('application')->middleware('throttle:10,1')->name('documents.store');
            Route::post('/{application}/documents/{document}/review', [AdmissionController::class, 'reviewDocument'])->whereNumber('application')->whereNumber('document')->middleware('throttle:20,1')->name('documents.review');
            Route::get('/{application}/documents/{document}', [AdmissionController::class, 'downloadDocument'])->whereNumber('document')->name('documents.download');
        }, 'admissions.manage');
        $context->registerPortalNavigation('admin', 'Admission Management', 'admin.admissions.index', ['icon' => 'graduation-cap', 'permission' => 'admissions.manage']);
        $context->registerPortalNavigation('branch', 'Admissions', 'branch.admissions.index', ['icon' => 'graduation-cap', 'permission' => 'admissions.manage']);
        $context->registerPortalNavigation('public', 'Apply for Admission', 'admission.public.apply', ['icon' => 'file-text']);
    }

    public function boot(PluginContext $context): void {}
};
