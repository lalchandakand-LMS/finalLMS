<?php
namespace Aloms\PhoneticsPractice;
use App\Contracts\Plugins\PluginContract;
use App\Services\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
require_once __DIR__.'/PhoneticsEntry.php';
require_once __DIR__.'/PhoneticsPracticeController.php';
return new class implements PluginContract {
 public function register(PluginContext $context): void {
  View::addNamespace('phonetics-practice', dirname(__DIR__).'/resources/views');
  $context->routesForPortal('admin','admin/phonetics','admin.phonetics.',function(): void {
   Route::get('/',[PhoneticsPracticeController::class,'adminIndex'])->name('index');
   Route::get('/create',[PhoneticsPracticeController::class,'create'])->name('create');
   Route::post('/',[PhoneticsPracticeController::class,'store'])->middleware('throttle:30,1')->name('store');
   Route::get('/{entry}/edit',[PhoneticsPracticeController::class,'edit'])->whereNumber('entry')->name('edit');
   Route::put('/{entry}',[PhoneticsPracticeController::class,'update'])->whereNumber('entry')->middleware('throttle:30,1')->name('update');
   Route::delete('/{entry}',[PhoneticsPracticeController::class,'destroy'])->whereNumber('entry')->middleware('throttle:20,1')->name('destroy');
  },'phonetics.manage');
  $context->routesForPortal('student','student/phonetics','student.phonetics.',function(): void {
   Route::get('/',[PhoneticsPracticeController::class,'studentIndex'])->name('index');
   Route::get('/search',[PhoneticsPracticeController::class,'search'])->middleware('throttle:60,1')->name('search');
   Route::get('/audio/{entry}/{accent}',[PhoneticsPracticeController::class,'audio'])->whereNumber('entry')->whereIn('accent',['uk','us'])->middleware('throttle:60,1')->name('audio');
  },'phonetics.practice');
  $context->registerPortalNavigation('admin','Phonetics Practice','admin.phonetics.index',['icon'=>'volume-2','permission'=>'phonetics.manage']);
  $context->registerPortalNavigation('student','Pronunciation Practice','student.phonetics.index',['icon'=>'audio-lines','permission'=>'phonetics.practice']);
 }
 public function boot(PluginContext $context): void {}
};
