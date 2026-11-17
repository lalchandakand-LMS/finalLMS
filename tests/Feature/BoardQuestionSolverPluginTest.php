<?php
namespace Tests\Feature;
use Aloms\BoardQuestionSolver\BoardQuestionSolverSettings;
use Aloms\BoardQuestionSolver\BoardQuestionSolverController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;
class BoardQuestionSolverPluginTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();
  View::addNamespace('board-question-solver',base_path('plugins/aec/board-question-solver/1.0.0/resources/views'));
  require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantSettings.php');
  require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantTokenBudget.php');
  require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantRequestLimit.php');
  require_once base_path('plugins/aec/board-question-solver/1.0.0/src/BoardQuestionSolverSettings.php');
  require_once base_path('plugins/aec/board-question-solver/1.0.0/src/BoardQuestionSolverController.php');
  if(!Schema::hasTable('board_question_solver_settings')){$migration=require base_path('plugins/aec/board-question-solver/1.0.0/migrations/2026_11_18_020000_create_board_question_solver_tables.php');$migration->up();}
  Route::get('/__test-board-solver/history',[BoardQuestionSolverController::class,'history']);
  Route::post('/__test-board-solver/solve',[BoardQuestionSolverController::class,'solve']);
 }
 public function test_students_can_only_see_their_own_board_question_history():void {
  $student=User::factory()->create(['role'=>'student']);
  $other=User::factory()->create(['role'=>'student']);
  DB::table('board_question_solutions')->insert([
   ['user_id'=>$student->id,'board'=>'Dhaka','exam_year'=>2025,'class_level'=>'SSC','subject'=>'English','question_type'=>'grammar','mode'=>'full','language'=>'bn','question'=>'My own question','solution'=>'My own solution','created_at'=>now(),'updated_at'=>now()],
   ['user_id'=>$other->id,'board'=>'Rajshahi','exam_year'=>2024,'class_level'=>'HSC','subject'=>'Math','question_type'=>'math','mode'=>'full','language'=>'en','question'=>'Private other question','solution'=>'Private other solution','created_at'=>now(),'updated_at'=>now()],
  ]);
  $this->actingAs($student)->get('/__test-board-solver/history')->assertOk()->assertViewHas('history',function($history){return $history->count()===1&&$history->first()->question==='My own question';})->assertSee('My own solution')->assertDontSee('Private other question');
 }
 public function test_solver_is_unavailable_until_admin_enables_it():void {
  $student=User::factory()->create(['role'=>'student']);
  $this->actingAs($student)->postJson('/__test-board-solver/solve',['subject'=>'English','question_type'=>'grammar','mode'=>'full','language'=>'bn','question'=>'Test question'])->assertNotFound();
 }
 public function test_plugin_is_disabled_by_default_and_admin_settings_are_bounded():void {
  $this->assertFalse((bool)BoardQuestionSolverSettings::get()['enabled']);
  BoardQuestionSolverSettings::save(['enabled'=>true,'allow_image_questions'=>false,'max_question_length'=>5000,'max_image_kb'=>2048,'daily_request_limit'=>7,'default_language'=>'en']);
  $settings=BoardQuestionSolverSettings::get();
  $this->assertTrue((bool)$settings['enabled']);
  $this->assertFalse((bool)$settings['allow_image_questions']);
  $this->assertSame(7,(int)$settings['daily_request_limit']);
  $this->assertSame('en',$settings['default_language']);
 }
}
