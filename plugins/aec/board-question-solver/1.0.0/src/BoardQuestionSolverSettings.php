<?php
namespace Aloms\BoardQuestionSolver;
use Illuminate\Support\Facades\DB;
final class BoardQuestionSolverSettings {
 public static function defaults():array{return ['enabled'=>false,'allow_image_questions'=>true,'max_question_length'=>4000,'max_image_kb'=>5120,'daily_request_limit'=>10,'default_language'=>'bn'];}
 public static function get():array{$row=DB::table('board_question_solver_settings')->where('id',1)->first();return array_replace(self::defaults(),$row?(array)$row:[]);}
 public static function save(array $values):void{DB::table('board_question_solver_settings')->updateOrInsert(['id'=>1],['enabled'=>(bool)$values['enabled'],'allow_image_questions'=>(bool)$values['allow_image_questions'],'max_question_length'=>(int)$values['max_question_length'],'max_image_kb'=>(int)$values['max_image_kb'],'daily_request_limit'=>(int)$values['daily_request_limit'],'default_language'=>$values['default_language'],'updated_at'=>now(),'created_at'=>now()]);}
}
