<?php
namespace Aloms\\BoardQuestionSolver;
use App\\Contracts\\Plugins\\PluginContract;
use App\\Services\\Plugins\\PluginContext;
use Illuminate\\Support\\Facades\\Route;
use Illuminate\\Support\\Facades\\View;
$dependency = base_path('plugins/aec/universal-ai-assistant/1.0.0/src/');
foreach (['AssistantSettings.php','AssistantTokenBudget.php','AssistantRequestLimit.php'] as $file) {
    $path = $dependency.$file;
    if (! is_file($path)) throw new \\RuntimeException('Board Question Solver requires the Universal AI Assistant plugin to be installed.');
    require_once $path;
}
require_once __DIR__.'/BoardQuestionSolverController.php';
require_once __DIR__.'/BoardQuestionSolverSettings.php';
return new class implements PluginContract {
 public function register(PluginContext $context): void {
  View::addNamespace('board-question-solver',dirname(__DIR__).'/resources/views');
  $context->routesForPortal('admin','admin/board-question-solver','admin.board-solver.',function():void {
   Route::get('/settings',[BoardQuestionSolverController::class,'settings'])->name('settings');
   Route::put('/settings',[BoardQuestionSolverController::class,'updateSettings'])->middleware('throttle:10,1')->name('settings.update');
   Route::get('/history',[BoardQuestionSolverController::class,'adminHistory'])->name('history');
  },'board-solver.configure');
  $context->routesForPortal('student','student/board-question-solver','student.board-solver.',function():void {
   Route::get('/',[BoardQuestionSolverController::class,'index'])->name('index');
   Route::post('/solve',[BoardQuestionSolverController::class,'solve'])->middleware('throttle:5,1')->name('solve');
   Route::get('/history',[BoardQuestionSolverController::class,'history'])->name('history');
  },'board-solver.use');
  $context->registerPortalNavigation('admin','Board Question Solver','admin.board-solver.settings',['icon'=>'graduation-cap','permission'=>'board-solver.configure']);
  $context->registerPortalNavigation('student','Board Question Solver','student.board-solver.index',['icon'=>'book-open-check','permission'=>'board-solver.use']);
 }
 public function boot(PluginContext $context): void {}
};
