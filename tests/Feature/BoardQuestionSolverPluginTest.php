<?php
namespace Tests\Feature;
use Aloms\BoardQuestionSolver\BoardQuestionSolverSettings;
use Aloms\BoardQuestionSolver\BoardQuestionSolverController;
use Aloms\UniversalAiAssistant\AssistantSettings;
use Aloms\UniversalAiAssistant\OpenAiCompatibleProvider;
use App\Contracts\Ai\AiProvider;
use App\Services\AiDeveloperProviderEndpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
  $aiMigrations=[
   '2026_11_15_180000_create_universal_ai_assistant_tables.php',
   '2026_11_15_190000_add_ai_assistant_conversation_retention.php',
   '2026_11_16_120000_create_ai_assistant_token_reservations.php',
   '2026_11_16_140000_add_ai_assistant_role_data_access.php',
   '2026_11_17_090000_create_ai_assistant_token_budget_tables.php',
   '2026_11_17_100000_create_ai_assistant_user_daily_requests.php',
  ];
  foreach($aiMigrations as $migrationFile){
   $migrationPath=base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/'.$migrationFile);
   $migration=require $migrationPath;
   $migration->up();
  }
  require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/OpenAiCompatibleProvider.php');

  require_once base_path('plugins/aec/board-question-solver/1.0.0/src/BoardQuestionSolverSettings.php');
  require_once base_path('plugins/aec/board-question-solver/1.0.0/src/BoardQuestionSolverController.php');
  if(!Schema::hasTable('board_question_solver_settings')){$migration=require base_path('plugins/aec/board-question-solver/1.0.0/migrations/2026_11_18_020000_create_board_question_solver_tables.php');$migration->up();}
  $reviewMigration=require base_path('plugins/aec/board-question-solver/1.0.0/migrations/2026_11_21_130000_add_admin_question_review.php');$reviewMigration->up();
  Route::get('/__test-board-solver/admin-history',[BoardQuestionSolverController::class,'adminHistory'])->name('admin.board-solver.history');
  Route::post('/__test-board-solver/{solution}/approve',[BoardQuestionSolverController::class,'approveSolution']);
  Route::post('/__test-board-solver/{solution}/reject',[BoardQuestionSolverController::class,'rejectSolution']);
  Route::get('/__test-admin-question-sets/{questionSet}/edit',fn()=>response('edit'))->name('admin.question-sets.edit');
  Route::get('/__test-board-solver/history',[BoardQuestionSolverController::class,'history']);
  Route::get('/__test-board-solver/index',[BoardQuestionSolverController::class,'index'])->name('student.board-solver.index');
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
 public function test_solver_uses_shared_provider_and_token_budget_for_reasoning_models():void {
  BoardQuestionSolverSettings::save(['enabled'=>true,'allow_image_questions'=>false,'max_question_length'=>5000,'max_image_kb'=>2048,'daily_request_limit'=>7,'default_language'=>'en']);
  AssistantSettings::save([
   'model'=>'o1-mini','base_url'=>'https://api.openai.com/v1','temperature'=>0.2,'max_tokens'=>1200,
   'global_enabled'=>true,'role_enabled'=>array_fill_keys(AssistantSettings::ROLES,false),
   'role_data_access'=>array_fill_keys(AssistantSettings::ROLES,false),
   'daily_request_limit'=>20,'daily_token_budget'=>50000,'retention_days'=>365,
  ],'test-secret-provider-key',false);
  $provider=\Mockery::mock(AiProvider::class);
  $provider->shouldReceive('complete')->once()->withArgs(function($key,$base,$model,$messages,$temperature,$maxTokens){
   return $key==='test-secret-provider-key'
    && $base==='https://api.openai.com/v1'
    && $model==='o1-mini'
    && $temperature===0.2
    && $maxTokens>0
    && isset($messages[1]['content'][0]['text']);
  })->andReturn(['content'=>'Worked solution','total_tokens'=>420]);
  app()->instance(AiProvider::class,$provider);

  $student=User::factory()->create(['role'=>'student']);
  $response=$this->actingAs($student)->postJson('/__test-board-solver/solve',[
   'board'=>'Dhaka','exam_year'=>2025,'class_level'=>'SSC','subject'=>'Mathematics',
   'question_type'=>'math','mode'=>'full','language'=>'en','question'=>'Solve 2x = 10',
  ]);
  $response->assertOk()->assertJsonPath('solution','Worked solution');
  $this->assertDatabaseHas('board_question_solutions',[
   'user_id'=>$student->id,'board'=>'Dhaka','exam_year'=>2025,'class_level'=>'SSC',
   'subject'=>'Mathematics','question_type'=>'math','mode'=>'full','language'=>'en',
   'question'=>'Solve 2x = 10','solution'=>'Worked solution',
  ]);
  $conversationId=DB::table('ai_assistant_conversations')->where('user_id',$student->id)->where('assistant_role','board_question_solver')->value('id');
  $this->assertNotNull($conversationId);
  $this->assertDatabaseHas('ai_assistant_token_reservations',['conversation_id'=>$conversationId,'status'=>'completed','actual_tokens'=>420,'prompt_persisted'=>true]);
  $this->assertDatabaseHas('ai_assistant_messages',['conversation_id'=>$conversationId,'message_role'=>'assistant','content'=>'Worked solution']);
 }


 public function test_admin_can_approve_a_solved_question_into_a_draft_exam_question_set():void {
  $student=User::factory()->create(['role'=>'student']);
  $admin=User::factory()->create(['role'=>'admin']);
  $solutionId=DB::table('board_question_solutions')->insertGetId([
   'user_id'=>$student->id,'board'=>'Dhaka','exam_year'=>2025,'class_level'=>'SSC',
   'subject'=>'Mathematics','question_type'=>'math','mode'=>'full','language'=>'bn',
   'question'=>'Solve 2x = 10','solution'=>'Step 1: divide both sides by 2. Final: x = 5.',
   'created_at'=>now(),'updated_at'=>now(),
  ]);
  $response=$this->actingAs($admin)->post('/__test-board-solver/'.$solutionId.'/approve',['marks'=>3,'admin_notes'=>'Checked by academic team.']);
  $setId=(int)DB::table('board_question_solutions')->where('id',$solutionId)->value('question_set_id');
  $response->assertRedirect(route('admin.question-sets.edit',$setId));
  $this->assertDatabaseHas('board_question_solutions',['id'=>$solutionId,'review_status'=>'approved','reviewed_by'=>$admin->id,'question_set_id'=>$setId]);
  $this->assertDatabaseHas('question_sets',['id'=>$setId,'status'=>'draft','created_by'=>$admin->id]);
  $this->assertDatabaseHas('question_set_questions',['question_set_id'=>$setId,'type'=>'short_answer','question'=>'Solve 2x = 10','correct_answer'=>'Step 1: divide both sides by 2. Final: x = 5.','marks'=>3]);
 }
 public function test_admin_can_reject_a_solution_with_a_reason_without_deleting_history():void {
  $student=User::factory()->create(['role'=>'student']);
  $admin=User::factory()->create(['role'=>'admin']);
  $solutionId=DB::table('board_question_solutions')->insertGetId([
   'user_id'=>$student->id,'subject'=>'English','question_type'=>'grammar','mode'=>'full','language'=>'en',
   'question'=>'Correct this sentence.','solution'=>'Suggested correction.','created_at'=>now(),'updated_at'=>now(),
  ]);
  $this->actingAs($admin)->post('/__test-board-solver/'.$solutionId.'/reject',['admin_notes'=>'Please verify the source question.'])->assertRedirect();
  $this->assertDatabaseHas('board_question_solutions',['id'=>$solutionId,'review_status'=>'rejected','reviewed_by'=>$admin->id,'admin_notes'=>'Please verify the source question.']);
 }
 public function test_student_cannot_approve_or_reject_solver_history():void {
  $student=User::factory()->create(['role'=>'student']);
  $owner=User::factory()->create(['role'=>'student']);
  $solutionId=DB::table('board_question_solutions')->insertGetId([
   'user_id'=>$owner->id,'subject'=>'English','question_type'=>'grammar','mode'=>'full','language'=>'en',
   'question'=>'Question','solution'=>'Answer','created_at'=>now(),'updated_at'=>now(),
  ]);
  $this->actingAs($student)->post('/__test-board-solver/'.$solutionId.'/approve',['marks'=>5])->assertForbidden();
  $this->assertDatabaseHas('board_question_solutions',['id'=>$solutionId,'review_status'=>'pending']);
 }

 public function test_reasoning_model_provider_uses_max_completion_tokens_without_temperature():void {
  $endpoint=new class extends AiDeveloperProviderEndpoint {
   public function curlOptionsFor(string $url):array{return [];}
  };
  $provider=new OpenAiCompatibleProvider($endpoint);
  Http::fake(['https://api.openai.com/v1/chat/completions'=>Http::response([
   'choices'=>[['message'=>['content'=>'Reasoned answer']]],
   'usage'=>['total_tokens'=>25],
  ],200)]);
  $provider->complete('test-secret-provider-key','https://api.openai.com/v1','o1-mini',[
   ['role'=>'user','content'=>'Solve this carefully.'],
  ],0.2,800);
  Http::assertSent(function($request){
   $payload=$request->data();
   return ($payload['max_completion_tokens']??null)===800
    && !array_key_exists('temperature',$payload)
    && !array_key_exists('max_tokens',$payload);
  });
 }

 public function test_full_solution_mode_builds_a_complete_step_by_step_prompt():void {
  $controller=app(BoardQuestionSolverController::class);
  $method=new \ReflectionMethod(BoardQuestionSolverController::class,'prompt');
  $prompt=$method->invoke($controller,['language'=>'en','mode'=>'full','board'=>'Dhaka','exam_year'=>2025,'class_level'=>'SSC','subject'=>'Mathematics','question_type'=>'math','question'=>'Solve 2x = 10']);
  $this->assertStringContainsString('complete, step-by-step worked solution',$prompt);
 }

}
