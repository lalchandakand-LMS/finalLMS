<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  if(!Schema::hasTable('board_question_solver_settings'))Schema::create('board_question_solver_settings',function(Blueprint $t):void{$t->unsignedTinyInteger('id')->primary();$t->boolean('enabled')->default(false);$t->boolean('allow_image_questions')->default(true);$t->unsignedInteger('max_question_length')->default(4000);$t->unsignedInteger('max_image_kb')->default(5120);$t->unsignedInteger('daily_request_limit')->default(10);$t->string('default_language',8)->default('bn');$t->timestamps();});
  if(!Schema::hasTable('board_question_solutions'))Schema::create('board_question_solutions',function(Blueprint $t):void{$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('board',80)->nullable();$t->unsignedSmallInteger('exam_year')->nullable();$t->string('class_level',40)->nullable();$t->string('subject',100);$t->string('question_type',24);$t->string('mode',16);$t->string('language',8);$t->longText('question');$t->longText('solution');$t->timestamps();$t->index(['user_id','created_at'],'board_solver_owner_created_idx');$t->index(['board','exam_year','subject'],'board_solver_catalog_idx');});
 }
 public function down():void {
  if(Schema::hasTable('board_question_solutions')&&DB::table('board_question_solutions')->exists())throw new RuntimeException('Board solver history exists; export or explicitly erase learning history before uninstalling.');
  Schema::dropIfExists('board_question_solutions');Schema::dropIfExists('board_question_solver_settings');
 }
};
