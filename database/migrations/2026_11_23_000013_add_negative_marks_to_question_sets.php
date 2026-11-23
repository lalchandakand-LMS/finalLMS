<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
        });
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
            $table->integer('marks_awarded')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('question_set_questions')->where('negative_marks', '>', 0)->exists()
            || DB::table('question_set_attempt_questions')->where('negative_marks', '>', 0)->exists()) {
            throw new RuntimeException('Cannot remove reusable question-set negative marks while configured penalties exist.');
        }
        if (DB::table('question_set_attempt_questions')->where('marks_awarded', '<', 0)->exists()) {
            throw new RuntimeException('Cannot remove signed quiz marks while negative results exist.');
        }
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('marks_awarded')->default(0)->change();
            $table->dropColumn('negative_marks');
        });
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->dropColumn('negative_marks');
        });
    }
};
