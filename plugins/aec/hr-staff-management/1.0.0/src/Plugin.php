<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/StaffProfile.php';
require_once __DIR__.'/StaffPayroll.php';
require_once __DIR__.'/StaffAttendance.php';
require_once __DIR__.'/StaffLeaveRequest.php';
require_once __DIR__.'/StaffJobOpening.php';
require_once __DIR__.'/StaffApplication.php';
require_once __DIR__.'/HrController.php';

return new class implements PluginContract {
    public function register(PluginContext $context): void
    {
        View::addNamespace('hr-staff-management', dirname(__DIR__).'/resources/views');
        foreach (['admin', 'branch'] as $portal) {
            $prefix = $portal === 'admin' ? 'admin' : 'branch';
            $context->routesForPortal($portal, $prefix.'/hr', $prefix.'.hr.', function (): void {
                Route::get('/', [HrController::class, 'index'])->name('index');
                Route::post('/staff', [HrController::class, 'storeStaff'])->middleware('throttle:20,1')->name('staff.store');
                Route::post('/attendance', [HrController::class, 'recordAttendance'])->middleware('throttle:60,1')->name('attendance.store');
                Route::post('/leave-requests', [HrController::class, 'requestLeave'])->middleware('throttle:20,1')->name('leave.store');
                Route::post('/payrolls', [HrController::class, 'recordPayroll'])->middleware('throttle:20,1')->name('payrolls.store');
            }, 'hr.manage');
        }
        $context->routesForPortal('public', 'careers', 'careers.', function (): void {
            Route::get('/', [HrController::class, 'publicCareers'])->name('index');
            Route::get('/{opening}', [HrController::class, 'publicCareerShow'])->whereNumber('opening')->name('show');
            Route::post('/{opening}/apply', [HrController::class, 'applyCareer'])->whereNumber('opening')->middleware('throttle:5,10')->name('apply');
        });
        $context->routesForPortal('admin', 'admin/hr/recruitment', 'admin.hr.recruitment.', function (): void {
            Route::get('/', [HrController::class, 'recruitmentIndex'])->name('index');
            Route::post('/openings', [HrController::class, 'storeJobOpening'])->middleware('throttle:20,1')->name('openings.store');
            Route::patch('/openings/{opening}/status', [HrController::class, 'updateJobOpeningStatus'])->whereNumber('opening')->name('openings.status');
            Route::post('/applications/{application}/review', [HrController::class, 'reviewApplication'])->whereNumber('application')->middleware('throttle:20,1')->name('applications.review');
            Route::get('/applications/{application}/resume', [HrController::class, 'downloadApplicationResume'])->whereNumber('application')->name('applications.resume');
        }, 'hr.recruitment.manage');
        $context->routesForPortal('teacher', 'teacher/hr', 'teacher.hr.', function (): void {
            Route::get('/', [HrController::class, 'teacherIndex'])->name('index');
            Route::post('/leave-requests', [HrController::class, 'teacherRequestLeave'])->middleware('throttle:10,1')->name('leave.store');
        }, 'hr.self-service');
        $context->routesForPortal('admin', 'admin/hr/payroll', 'admin.hr.payroll.', function (): void {
            Route::post('/{payroll}/mark-paid', [HrController::class, 'markPayrollPaid'])->whereNumber('payroll')->middleware('throttle:10,1')->name('mark-paid');
        }, 'hr.payroll.disburse');
        $context->routesForPortal('admin', 'admin/hr/leave', 'admin.hr.leave.', function (): void {
            Route::post('/{leave}/review', [HrController::class, 'reviewLeave'])->whereNumber('leave')->middleware('throttle:20,1')->name('review');
        }, 'hr.manage');
        $context->registerPortalNavigation('public', 'Careers', 'careers.index');
        $context->registerPortalNavigation('admin', 'Recruitment', 'admin.hr.recruitment.index', ['icon' => 'briefcase', 'permission' => 'hr.recruitment.manage']);
        $context->registerPortalNavigation('teacher', 'My HR & Leave', 'teacher.hr.index', ['icon' => 'calendar', 'permission' => 'hr.self-service']);
        $context->registerPortalNavigation('admin', 'HR & Payroll', 'admin.hr.index', ['icon' => 'users', 'permission' => 'hr.manage']);
        $context->registerPortalNavigation('branch', 'HR & Payroll', 'branch.hr.index', ['icon' => 'users', 'permission' => 'hr.manage']);
    }

    public function boot(PluginContext $context): void {}
};
