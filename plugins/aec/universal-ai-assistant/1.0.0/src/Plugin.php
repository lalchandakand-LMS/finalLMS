<?php

namespace Aloms\UniversalAiAssistant;

use App\Contracts\Ai\AiProvider;
use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

require_once __DIR__.'/AssistantSettings.php';
require_once __DIR__.'/OpenAiCompatibleProvider.php';
require_once __DIR__.'/AssistantTokenBudget.php';
require_once __DIR__.'/AssistantContextBuilder.php';
require_once __DIR__.'/AssistantAudit.php';
require_once __DIR__.'/AssistantStreamRedactor.php';
require_once __DIR__.'/AdminSettingsController.php';
require_once __DIR__.'/AssistantController.php';
require_once __DIR__.'/AgentActionWorkflow.php';
require_once __DIR__.'/AgentActionController.php';

return new class implements PluginContract
{
    public function register(PluginContext $context): void
    {
        $context->singleton(AiProvider::class, OpenAiCompatibleProvider::class);
        View::addNamespace('universal-ai-assistant', dirname(__DIR__).'/resources/views');

        $context->routesForPortal('admin', 'admin/ai-assistant', 'admin.ai-assistant.', function (): void {
            Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
            Route::get('/models', [AdminSettingsController::class, 'models'])->middleware('throttle:10,1')->name('models');
            Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('throttle:10,1')->name('settings.update');
            Route::get('/', [AssistantController::class, 'index'])->name('index');
            Route::post('/messages', [AssistantController::class, 'send'])->middleware('throttle:10,1')->name('messages.store');
            Route::get('/proposals', [AgentActionController::class, 'index'])->name('proposals.index');
            Route::post('/proposals/{proposal}/approve', [AgentActionController::class, 'approve'])->middleware('throttle:20,1')->name('proposals.approve');
            Route::post('/proposals/{proposal}/reject', [AgentActionController::class, 'reject'])->middleware('throttle:20,1')->name('proposals.reject');
        }, 'ai-assistant.configure');

        foreach ([
            'branch' => ['branch/ai-assistant', 'branch.ai-assistant.'],
            'teacher' => ['teacher/ai-assistant', 'teacher.ai-assistant.'],
            'student' => ['student/ai-assistant', 'student.ai-assistant.'],
            'guardian' => ['guardian/ai-assistant', 'guardian.ai-assistant.'],
            'support_staff' => ['support/ai-assistant', 'support.ai-assistant.'],
        ] as $portal => [$prefix, $namePrefix]) {
            $context->routesForPortal($portal, $prefix, $namePrefix, function (): void {
                Route::get('/', [AssistantController::class, 'index'])->name('index');
                Route::post('/messages', [AssistantController::class, 'send'])->middleware('throttle:10,1')->name('messages.store');
                if ($portal === 'teacher') {
                    Route::get('/proposals', [AgentActionController::class, 'index'])->name('proposals.index');
                    Route::post('/proposals/{proposal}/approve', [AgentActionController::class, 'approve'])->middleware('throttle:20,1')->name('proposals.approve');
                    Route::post('/proposals/{proposal}/reject', [AgentActionController::class, 'reject'])->middleware('throttle:20,1')->name('proposals.reject');
                }
                if ($portal === 'student') {
                    Route::get('/proposals', [AgentActionController::class, 'index'])->name('proposals.index');
                    Route::post('/proposals', [AgentActionController::class, 'storeStudyPlan'])->middleware('throttle:10,1')->name('proposals.store');
                }
            }, 'ai-assistant.use');
        }
    }

    public function boot(PluginContext $context): void
    {
        // No scheduled work or provider calls are made during plugin boot.
    }
};
