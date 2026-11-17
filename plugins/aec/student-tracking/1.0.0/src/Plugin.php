<?php

namespace Aloms\StudentTracking;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/StudentTrackingController.php';
require_once __DIR__.'/StudentTrackingSettingsController.php';

return new class implements PluginContract
{
    public function register(PluginContext $context): void
    {
        View::addNamespace('student-tracking', dirname(__DIR__).'/resources/views');

        $portals = [
            'admin' => ['admin/student-tracking', 'admin.student-tracking.', 'Student Tracking'],
            'branch' => ['branch/student-tracking', 'branch.student-tracking.', 'Branch Student Tracking'],
            'teacher' => ['teacher/student-tracking', 'teacher.student-tracking.', 'My Students'],
            'student' => ['student/tracking', 'student.tracking.', 'My Learning Tracking'],
            'guardian' => ['guardian/student-tracking', 'guardian.student-tracking.', 'Children Tracking'],
        ];

        foreach ($portals as $portal => [$prefix, $namePrefix, $label]) {
            $context->routesForPortal($portal, $prefix, $namePrefix, function (): void {
                Route::get('/export', [StudentTrackingController::class, 'export'])
                    ->middleware('throttle:5,1')
                    ->name('export');
                Route::get('/', [StudentTrackingController::class, 'index'])->name('index');
                Route::get('/{student}', [StudentTrackingController::class, 'show'])
                    ->whereNumber('student')
                    ->name('show');
            }, 'student-tracking.view');

            $context->registerPortalNavigation(
                $portal,
                $label,
                $namePrefix.'index',
                ['icon' => '📊', 'permission' => 'student-tracking.view'],
            );
        }

        $context->routesForPortal('admin', 'admin/student-tracking/settings', 'admin.student-tracking.settings.', function (): void {
            Route::get('/', [StudentTrackingSettingsController::class, 'index'])->name('index');
            Route::put('/', [StudentTrackingSettingsController::class, 'update'])
                ->middleware('throttle:10,1')
                ->name('update');
        }, 'student-tracking.configure');

        $context->registerPortalNavigation(
            'admin',
            'Tracking Risk Rules',
            'admin.student-tracking.settings.index',
            ['icon' => '⚙️', 'permission' => 'student-tracking.configure'],
        );
    }

    public function boot(PluginContext $context): void
    {
        // Read-only reporting plugin. It does not collect GPS or background location data.
    }
};
