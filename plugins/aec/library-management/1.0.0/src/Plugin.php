<?php

declare(strict_types=1);

namespace Aloms\LibraryManagement;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/LibraryBook.php';
require_once __DIR__.'/LibraryLoan.php';
require_once __DIR__.'/LibraryController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('library-management', dirname(__DIR__).'/resources/views');
        $context->routesForPortal('admin', 'admin/library', 'admin.library.', function (): void {
            Route::get('/', [LibraryController::class, 'index'])->name('index');
            Route::post('/books', [LibraryController::class, 'storeBook'])->middleware('throttle:20,1')->name('books.store');
            Route::post('/loans', [LibraryController::class, 'issueLoan'])->middleware('throttle:20,1')->name('loans.issue');
            Route::post('/loans/{loan}/return', [LibraryController::class, 'returnLoan'])->whereNumber('loan')->middleware('throttle:20,1')->name('loans.return');
        }, 'library.manage');
        $context->routesForPortal('branch', 'branch/library', 'branch.library.', function (): void {
            Route::get('/', [LibraryController::class, 'index'])->name('index');
            Route::post('/books', [LibraryController::class, 'storeBook'])->middleware('throttle:20,1')->name('books.store');
            Route::post('/loans', [LibraryController::class, 'issueLoan'])->middleware('throttle:20,1')->name('loans.issue');
            Route::post('/loans/{loan}/return', [LibraryController::class, 'returnLoan'])->whereNumber('loan')->middleware('throttle:20,1')->name('loans.return');
        }, 'library.manage');
        $context->registerPortalNavigation('admin', 'Physical Library', 'admin.library.index', ['icon' => 'book-open', 'permission' => 'library.manage']);
        $context->registerPortalNavigation('branch', 'Physical Library', 'branch.library.index', ['icon' => 'book-open', 'permission' => 'library.manage']);
    }

    public function boot(PluginContext $context): void {}
};
