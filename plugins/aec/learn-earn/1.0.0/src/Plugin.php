<?php

namespace Aloms\LearnEarn;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/LearnEarnCategory.php';
require_once __DIR__.'/LearnEarnQuestion.php';
require_once __DIR__.'/AdminLearnEarnController.php';
require_once __DIR__.'/LearnEarnExam.php';
require_once __DIR__.'/LearnEarnExamQuestion.php';
require_once __DIR__.'/AdminLearnEarnExamController.php';
require_once __DIR__.'/LearnEarnExamAttempt.php';
require_once __DIR__.'/LearnEarnAttemptQuestion.php';
require_once __DIR__.'/LearnEarnExamAnswer.php';
require_once __DIR__.'/LearnEarnExamEntitlement.php';
require_once __DIR__.'/LearnEarnScorer.php';
require_once __DIR__.'/StudentLearnEarnController.php';

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

            Route::get('/exams', [AdminLearnEarnExamController::class, 'index'])->name('exams.index');
            Route::get('/exams/create', [AdminLearnEarnExamController::class, 'create'])->name('exams.create');
            Route::post('/exams', [AdminLearnEarnExamController::class, 'store'])->middleware('throttle:20,1')->name('exams.store');
            Route::patch('/exams/{exam}/archive', [AdminLearnEarnExamController::class, 'archive'])->whereNumber('exam')->name('exams.archive');

            Route::post('/categories', [AdminLearnEarnController::class, 'storeCategory'])->middleware('throttle:20,1')->name('categories.store');
            Route::put('/categories/{category}', [AdminLearnEarnController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:20,1')->name('categories.update');
            Route::patch('/categories/{category}/archive', [AdminLearnEarnController::class, 'archiveCategory'])->whereNumber('category')->name('categories.archive');
        }, 'learn-earn.manage');

        $context->routesForPortal('student', 'student/learn-earn', 'student.learn-earn.', function (): void {
            Route::get('/exams', [StudentLearnEarnController::class, 'index'])->name('exams.index');
            Route::get('/exams/{exam:slug}', [StudentLearnEarnController::class, 'show'])->name('exams.show');
            Route::post('/exams/{exam:slug}/checkout/bkash', [StudentLearnEarnController::class, 'checkoutBkash'])->middleware('throttle:5,1')->name('exams.checkout.bkash');
            Route::post('/exams/{exam:slug}/start', [StudentLearnEarnController::class, 'start'])->middleware('throttle:5,1')->name('exams.start');
            Route::get('/attempts/{attempt}', [StudentLearnEarnController::class, 'attempt'])->whereNumber('attempt')->name('attempts.show');
            Route::post('/attempts/{attempt}/submit', [StudentLearnEarnController::class, 'submit'])->whereNumber('attempt')->middleware('throttle:5,1')->name('attempts.submit');
            Route::get('/attempts/{attempt}/result', [StudentLearnEarnController::class, 'result'])->whereNumber('attempt')->name('attempts.result');
            Route::get('/history', [StudentLearnEarnController::class, 'history'])->name('history');
        }, 'learn-earn.take-exams');

        $context->routesForPortal('public', 'learn-earn/exams', 'learn-earn.exams.', function (): void {
            Route::get('/', [StudentLearnEarnController::class, 'publicIndex'])->name('index');
            Route::get('/{exam:slug}', [StudentLearnEarnController::class, 'publicShow'])->name('show');
        });

        $context->registerPortalNavigation('admin', 'Learn & Earn Questions', 'admin.learn-earn.questions.index', [
            'icon' => '📝',
            'permission' => 'learn-earn.manage',
        ]);
        $context->registerPortalNavigation('admin', 'Learn & Earn Exams', 'admin.learn-earn.exams.index', [
            'icon' => '🧪',
            'permission' => 'learn-earn.manage',
        ]);
        $context->registerPortalNavigation('student', 'Learn & Earn', 'student.learn-earn.exams.index', [
            'icon' => '🧪',
            'permission' => 'learn-earn.take-exams',
        ]);
        $context->registerPortalNavigation('public', 'Exams', 'learn-earn.exams.index', [
            'icon' => '🧪',
        ]);
    }

    public function boot(PluginContext $context): void
    {
        // No external services, background jobs or provider calls are started during plugin boot.
    }
};
