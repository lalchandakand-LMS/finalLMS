<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/StaffProfile.php';
require_once __DIR__.'/StaffPayroll.php';
require_once __DIR__.'/HrController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('hr-staff-management', dirname(__DIR__).'/resources/views');
        $context->routesForPortal('admin', 'admin/hr', 'admin.hr.', function (): void {
            Route::get('/', [HrController::class, 'index'])->name('index');
            Route::post('/staff', [HrController::class, 'storeStaff'])->middleware('throttle:20,1')->name('staff.store');
            Route::post('/payrolls', [HrController::class, 'recordPayroll'])->middleware('throttle:20,1')->name('payrolls.store');
        }, 'hr.manage');
        $context->routesForPortal('admin', 'admin/hr/payroll', 'admin.hr.payroll.', function (): void {
            Route::post('/{payroll}/mark-paid', [HrController::class, 'markPayrollPaid'])->whereNumber('payroll')->middleware('throttle:10,1')->name('mark-paid');
        }, 'hr.payroll.disburse');
        $context->routesForPortal('branch', 'branch/hr', 'branch.hr.', function (): void {
            Route::get('/', [HrController::class, 'index'])->name('index');
            Route::post('/staff', [HrController::class, 'storeStaff'])->middleware('throttle:20,1')->name('staff.store');
            Route::post('/payrolls', [HrController::class, 'recordPayroll'])->middleware('throttle:20,1')->name('payrolls.store');
        }, 'hr.manage');
        $context->registerPortalNavigation('admin', 'HR & Payroll', 'admin.hr.index', ['icon' => 'users', 'permission' => 'hr.manage']);
        $context->registerPortalNavigation('branch', 'HR & Payroll', 'branch.hr.index', ['icon' => 'users', 'permission' => 'hr.manage']);
    }

    public function boot(PluginContext $context): void {}
};
