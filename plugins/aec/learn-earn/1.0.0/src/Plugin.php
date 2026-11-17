<?php

namespace Aloms\LearnEarn;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/LearnEarnCategory.php';
require_once __DIR__.'/LearnEarnQuestion.php';
require_once __DIR__.'/AdminLearnEarnController.php';

return new class implements PluginContract
{
    public function register(PluginContext $context): void
    {
        View::addNamespace('learn-earn', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/learn-earn', 'admin.learn-earn.', function (): void {
            Route::get('/questions', [AdminLearnEarnController::class, 'index'])->name('questions.index');
            Route::get('/questions/create', [AdminLearnEarnController::class, 'create'])->name('questions.create');
            Route::post('/questions', [AdminLearnEarnController::class, 'store'])->middleware('throttle:30,1')->name('questions.store');
            Route::get('/questions/{question}/edit', [AdminLearnEarnController::class, 'edit'])->whereNumber('question')->name('questions.edit');
            Route::put('/questions/{question}', [AdminLearnEarnController::class, 'update'])->whereNumber('question')->middleware('throttle:30,1')->name('questions.update');
            Route::patch('/questions/{question}/archive', [AdminLearnEarnController::class, 'archive'])->whereNumber('question')->name('questions.archive');

            Route::post('/categories', [AdminLearnEarnController::class, 'storeCategory'])->middleware('throttle:20,1')->name('categories.store');
            Route::put('/categories/{category}', [AdminLearnEarnController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:20,1')->name('categories.update');
            Route::patch('/categories/{category}/archive', [AdminLearnEarnController::class, 'archiveCategory'])->whereNumber('category')->name('categories.archive');
        }, 'learn-earn.manage');

        $context->registerPortalNavigation('admin', 'Learn & Earn', 'admin.learn-earn.questions.index', [
            'icon' => '📝',
            'permission' => 'learn-earn.manage',
        ]);
    }

    public function boot(PluginContext $context): void
    {
        // No external services, background jobs or provider calls are started during plugin boot.
    }
};
