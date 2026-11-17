<?php

namespace Aloms\PrepQuestionBank;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/PrepQuestionBankController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('prep-question-bank', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/prep-question-bank', 'admin.prep-question-bank.', function (): void {
            Route::get('/', [PrepQuestionBankController::class, 'index'])->name('index');
            Route::get('/questions/create', [PrepQuestionBankController::class, 'createQuestion'])->name('questions.create');
            Route::post('/questions', [PrepQuestionBankController::class, 'storeQuestion'])->middleware('throttle:30,1')->name('questions.store');
            Route::get('/questions/{question}/edit', [PrepQuestionBankController::class, 'editQuestion'])->name('questions.edit');
            Route::put('/questions/{question}', [PrepQuestionBankController::class, 'updateQuestion'])->middleware('throttle:30,1')->name('questions.update');
            Route::delete('/questions/{question}', [PrepQuestionBankController::class, 'destroyQuestion'])->name('questions.destroy');
            Route::get('/sets', [PrepQuestionBankController::class, 'sets'])->name('sets.index');
            Route::get('/sets/create', [PrepQuestionBankController::class, 'createSet'])->name('sets.create');
            Route::post('/sets', [PrepQuestionBankController::class, 'storeSet'])->middleware('throttle:15,1')->name('sets.store');
            Route::get('/sets/{set}', [PrepQuestionBankController::class, 'showSet'])->name('sets.show');
            Route::delete('/sets/{set}', [PrepQuestionBankController::class, 'destroySet'])->name('sets.destroy');
        }, 'prep-question-bank.manage');

        $context->registerPortalNavigation('admin', 'BCS / Bank / Admission Question Bank', 'admin.prep-question-bank.index', [
            'icon' => 'book-open-check',
            'permission' => 'prep-question-bank.manage',
        ]);
    }

    public function boot(PluginContext $context): void {}
};
