<?php

namespace Aloms\UniversalAiAssistant;

use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/AssistantSettingsController.php';
require_once __DIR__.'/AssistantController.php';
require_once __DIR__.'/AssistantService.php';

final class UniversalAiAssistantPlugin implements PluginContract
{
    public function register(PluginContext $context): void
    {
        $context->singleton(AssistantService::class);
        app('view')->addNamespace('universal-ai-assistant', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/ai-assistant', 'admin.ai-assistant.', function (): void {
            Route::get('/settings', [AssistantSettingsController::class, 'edit'])->name('settings');
            Route::put('/settings', [AssistantSettingsController::class, 'update'])->name('settings.update');
            Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
            Route::post('/assistant/chat', [AssistantController::class, 'chat'])->middleware('throttle:10,1')->name('assistant.chat');
        });

        $portals = [
            'branch' => ['branch/ai-assistant', 'branch.ai-assistant.'],
            'teacher' => ['teacher/ai-assistant', 'teacher.ai-assistant.'],
            'student' => ['student/ai-assistant', 'student.ai-assistant.'],
            'guardian' => ['guardian/ai-assistant', 'guardian.ai-assistant.'],
            'support_staff' => ['staff/ai-assistant', 'staff.ai-assistant.'],
        ];

        foreach ($portals as $portal => [$prefix, $namePrefix]) {
            $context->routesForPortal($portal, $prefix, $namePrefix, function (): void {
                Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');
                Route::post('/assistant/chat', [AssistantController::class, 'chat'])->middleware('throttle:10,1')->name('assistant.chat');
            });
        }
    }

    public function boot(PluginContext $context): void
    {
        // No background jobs, schedules or private LMS data access are enabled by default.
    }
}

return new UniversalAiAssistantPlugin();