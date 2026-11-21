<?php

declare(strict_types=1);

namespace Aloms\ReferralIncome;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/ReferralIncomeService.php';
require_once __DIR__.'/ReferralIncomeController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('referral-income', dirname(__DIR__).'/resources/views');
        $context->routesForPortal('student', 'student/referrals', 'student.referrals.', function (): void {
            Route::get('/', [ReferralIncomeController::class, 'dashboard'])->name('dashboard');
            Route::post('/apply', [ReferralIncomeController::class, 'apply'])->middleware('throttle:5,1')->name('apply');
            Route::post('/payout', [ReferralIncomeController::class, 'requestPayout'])->middleware('throttle:3,1')->name('payout');
        }, 'referral.use');
        $context->routesForPortal('teacher', 'teacher/referrals', 'teacher.referrals.', function (): void {
            Route::get('/', [ReferralIncomeController::class, 'dashboard'])->name('dashboard');
            Route::post('/apply', [ReferralIncomeController::class, 'apply'])->middleware('throttle:5,1')->name('apply');
            Route::post('/payout', [ReferralIncomeController::class, 'requestPayout'])->middleware('throttle:3,1')->name('payout');
        }, 'referral.use');
        $context->routesForPortal('admin', 'admin/referrals', 'admin.referrals.', function (): void {
            Route::get('/', [ReferralIncomeController::class, 'adminIndex'])->name('index');
            Route::post('/commissions/{commission}/review', [ReferralIncomeController::class, 'reviewCommission'])->whereNumber('commission')->middleware('throttle:20,1')->name('commissions.review');
            Route::post('/payouts/{payout}/review', [ReferralIncomeController::class, 'reviewPayout'])->whereNumber('payout')->middleware('throttle:10,1')->name('payouts.review');
        }, 'referral.manage');
        $context->registerPortalNavigation('student', 'Referral Income', 'student.referrals.dashboard', ['icon' => 'users', 'permission' => 'referral.use']);
        $context->registerPortalNavigation('teacher', 'Referral Income', 'teacher.referrals.dashboard', ['icon' => 'users', 'permission' => 'referral.use']);
        $context->registerPortalNavigation('admin', 'Referral Income', 'admin.referrals.index', ['icon' => 'wallet', 'permission' => 'referral.manage']);
    }

    public function boot(PluginContext $context): void {}
};
